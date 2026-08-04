<?php

declare(strict_types=1);

namespace App\Catalog\Application;

use App\Catalog\Application\Coverage\CatalogCoverageDataSource;
use App\Catalog\Application\Coverage\CatalogCoverageEvaluator;
use App\Catalog\Application\Coverage\CatalogCoverageMeasurement;
use App\Catalog\Application\Coverage\CatalogSetCoverageMeasurement;
use App\Catalog\Application\Exception\InvalidCatalogCoverageEvidence;
use App\Catalog\Domain\Entity\CatalogCoverageSnapshot;
use App\Catalog\Domain\Entity\CatalogDiscrepancyEntityType;
use App\Catalog\Domain\Entity\CatalogDiscrepancySeverity;
use App\Catalog\Domain\Entity\CatalogDiscrepancyType;
use App\Catalog\Domain\Entity\CatalogImportRun;
use App\Catalog\Domain\Repository\CatalogCoverageSnapshotRepository;
use App\Catalog\Domain\Repository\CatalogDiscrepancyRepository;
use App\Catalog\Domain\Repository\CatalogImportRunRepository;
use App\Catalog\Domain\ValueObject\CatalogCoverageManifest;
use App\Catalog\Domain\ValueObject\CatalogCoverageManifestSet;
use App\Catalog\Domain\ValueObject\CatalogDiscrepancyObservation;
use App\Identity\Domain\Entity\User;
use Psr\Log\LoggerInterface;

final readonly class GenerateCatalogCoverageSnapshot
{
    public function __construct(
        private CatalogImportRunRepository $importRuns,
        private CatalogCoverageSnapshotRepository $snapshots,
        private CatalogDiscrepancyRepository $discrepancies,
        private CatalogDiscrepancyRecorder $discrepancyRecorder,
        private CatalogCoverageDataSource $dataSource,
        private CatalogCoverageEvaluator $evaluator,
        private LoggerInterface $logger,
    ) {
    }

    public function execute(
        User $approver,
        string $importRunId,
        CatalogCoverageManifest $manifest,
    ): CatalogCoverageSnapshot {
        $run = $this->importRuns->get($importRunId);
        if (!$run instanceof CatalogImportRun) {
            throw new InvalidCatalogCoverageEvidence('The referenced catalog import run does not exist.');
        }
        $this->assertEvidenceMatchesRun($run, $manifest);

        $checksum = $manifest->checksum();
        $existing = $this->snapshots->findByManifest($importRunId, $checksum);
        if ($existing instanceof CatalogCoverageSnapshot) {
            return $existing;
        }

        $measurement = $this->dataSource->measure($manifest);
        $completeSetCount = $this->recordMeasuredDiscrepancies($run, $manifest, $measurement);
        $unresolvedCriticalCount = $this->discrepancies->countBlocking(
            $manifest->provider,
            $run->getScopeKey(),
            $manifest->language,
            $manifest->region,
        );
        $externallyBlockedCriticalCount = $this->discrepancies->countBlockedExternal(
            $manifest->provider,
            $run->getScopeKey(),
            $manifest->language,
            $manifest->region,
        );
        $evaluation = $this->evaluator->evaluate(
            $manifest,
            $measurement,
            $completeSetCount,
            $unresolvedCriticalCount,
            $externallyBlockedCriticalCount,
        );

        $snapshot = new CatalogCoverageSnapshot(
            importRun: $run,
            approvedBy: $approver,
            approvedByIdentifier: $approver->getId()->toRfc4122(),
            provider: $manifest->provider,
            region: $manifest->region,
            language: $manifest->language,
            evidenceSource: $manifest->evidenceSource,
            evidenceVersion: $manifest->evidenceVersion,
            evidenceReference: $manifest->evidenceReference,
            dataCutoff: $manifest->dataCutoff,
            manifestChecksum: $checksum,
            dimensions: $evaluation->dimensions,
            releaseGateStatus: $evaluation->releaseGateStatus,
            blockingReasons: $evaluation->blockingReasons,
            unresolvedCriticalDiscrepancyCount: $unresolvedCriticalCount,
            externallyBlockedCriticalDiscrepancyCount: $externallyBlockedCriticalCount,
            duplicateSetCount: $measurement->duplicateSetCount,
            duplicateCardCount: $measurement->duplicateCardCount,
            orphanCardCount: $measurement->orphanCardCount,
        );
        $this->snapshots->save($snapshot);
        $this->logger->info('Catalog coverage snapshot generated.', [
            'snapshot_id' => $snapshot->getId()->toRfc4122(),
            'provider' => $manifest->provider,
            'region' => $manifest->region,
            'language' => $manifest->language,
            'release_gate_status' => $snapshot->getReleaseGateStatus()->value,
            'unresolved_critical_discrepancies' => $unresolvedCriticalCount,
        ]);

        return $snapshot;
    }

    private function assertEvidenceMatchesRun(
        CatalogImportRun $run,
        CatalogCoverageManifest $manifest,
    ): void {
        if (!$run->getStatus()->isSuccessful()) {
            throw new InvalidCatalogCoverageEvidence('Coverage evidence requires a successful import run.');
        }
        if ($run->getProvider() !== $manifest->provider) {
            throw new InvalidCatalogCoverageEvidence('Coverage provider does not match the import run.');
        }
        if ($run->getSourceVersion() === null) {
            throw new InvalidCatalogCoverageEvidence('Coverage evidence requires a versioned catalog import source.');
        }
        $runDataCutoff = $run->getDataCutoff();
        if ($runDataCutoff === null || $runDataCutoff->format(DATE_ATOM) !== $manifest->dataCutoff->format(DATE_ATOM)) {
            throw new InvalidCatalogCoverageEvidence('Coverage data cutoff must match the catalog import run.');
        }

        $scope = $run->getDataScope();
        $languages = $scope['languages'] ?? null;
        if (!is_array($languages) || !in_array($manifest->language, $languages, true)) {
            throw new InvalidCatalogCoverageEvidence('Coverage language is outside the import run scope.');
        }

        $scopeSetIds = $scope['setIds'] ?? null;
        if (!is_array($scopeSetIds) || $scopeSetIds === []) {
            return;
        }

        $manifestSetIds = array_map(
            static fn (CatalogCoverageManifestSet $set): string => $set->externalId,
            $manifest->sets,
        );
        sort($scopeSetIds, SORT_STRING);
        sort($manifestSetIds, SORT_STRING);
        if ($scopeSetIds !== $manifestSetIds) {
            throw new InvalidCatalogCoverageEvidence('Coverage set IDs must exactly match the bounded import scope.');
        }
    }

    private function recordMeasuredDiscrepancies(
        CatalogImportRun $run,
        CatalogCoverageManifest $manifest,
        CatalogCoverageMeasurement $measurement,
    ): int {
        $measurementsByCode = [];
        foreach ($measurement->sets as $setMeasurement) {
            $measurementsByCode[$setMeasurement->catalogCode] = $setMeasurement;
        }

        $completeSetCount = 0;
        foreach ($manifest->sets as $expectedSet) {
            $catalogCode = $expectedSet->catalogCode($manifest->provider, $manifest->language);
            $actualSet = $measurementsByCode[$catalogCode] ?? null;
            if (!$actualSet instanceof CatalogSetCoverageMeasurement) {
                $this->record(
                    $run,
                    $manifest,
                    identity: 'missing-set:'.$expectedSet->externalId,
                    entityType: CatalogDiscrepancyEntityType::Set,
                    type: CatalogDiscrepancyType::MissingSet,
                    severity: CatalogDiscrepancySeverity::Critical,
                    message: sprintf('Expected set "%s" is absent from the catalog.', $expectedSet->externalId),
                    externalReference: $expectedSet->externalId,
                );
                continue;
            }

            if ($actualSet->cardCount === $expectedSet->expectedCardCount) {
                ++$completeSetCount;
            } elseif ($actualSet->cardCount < $expectedSet->expectedCardCount) {
                $this->record(
                    $run,
                    $manifest,
                    identity: 'missing-cards:'.$expectedSet->externalId,
                    entityType: CatalogDiscrepancyEntityType::Set,
                    type: CatalogDiscrepancyType::MissingCard,
                    severity: CatalogDiscrepancySeverity::Critical,
                    message: sprintf(
                        'Set "%s" contains %d of %d expected cards.',
                        $expectedSet->externalId,
                        $actualSet->cardCount,
                        $expectedSet->expectedCardCount,
                    ),
                    externalReference: $expectedSet->externalId,
                );
            } else {
                $this->record(
                    $run,
                    $manifest,
                    identity: 'conflicting-total:'.$expectedSet->externalId,
                    entityType: CatalogDiscrepancyEntityType::Set,
                    type: CatalogDiscrepancyType::ConflictingTotal,
                    severity: CatalogDiscrepancySeverity::Critical,
                    message: sprintf(
                        'Set "%s" contains %d cards while approved evidence expects %d.',
                        $expectedSet->externalId,
                        $actualSet->cardCount,
                        $expectedSet->expectedCardCount,
                    ),
                    externalReference: $expectedSet->externalId,
                );
            }

            if ($actualSet->stableOrderCardCount < $actualSet->cardCount) {
                $this->record(
                    $run,
                    $manifest,
                    identity: 'missing-order:'.$expectedSet->externalId,
                    entityType: CatalogDiscrepancyEntityType::Set,
                    type: CatalogDiscrepancyType::MissingCardNumber,
                    severity: CatalogDiscrepancySeverity::Critical,
                    message: sprintf('Set "%s" contains cards without a stable order key.', $expectedSet->externalId),
                    externalReference: $expectedSet->externalId,
                );
            }
        }

        $this->recordAggregateCoverageGaps($run, $manifest, $measurement);

        return $completeSetCount;
    }

    private function recordAggregateCoverageGaps(
        CatalogImportRun $run,
        CatalogCoverageManifest $manifest,
        CatalogCoverageMeasurement $measurement,
    ): void {
        if (
            $manifest->expectedImageCardCount !== null
            && $measurement->imageCardCount() < $manifest->expectedImageCardCount
        ) {
            $this->record(
                $run,
                $manifest,
                identity: 'missing-images',
                entityType: CatalogDiscrepancyEntityType::Image,
                type: CatalogDiscrepancyType::MissingImage,
                severity: CatalogDiscrepancySeverity::Medium,
                message: sprintf(
                    'The scope contains images for %d of %d expected cards.',
                    $measurement->imageCardCount(),
                    $manifest->expectedImageCardCount,
                ),
            );
        }
        if (
            $manifest->expectedArtistCardCount !== null
            && $measurement->artistCardCount() < $manifest->expectedArtistCardCount
        ) {
            $this->record(
                $run,
                $manifest,
                identity: 'missing-artists',
                entityType: CatalogDiscrepancyEntityType::Artist,
                type: CatalogDiscrepancyType::MissingArtist,
                severity: CatalogDiscrepancySeverity::High,
                message: sprintf(
                    'The scope contains artist links for %d of %d expected cards.',
                    $measurement->artistCardCount(),
                    $manifest->expectedArtistCardCount,
                ),
            );
        }
        if ($measurement->duplicateSetCount > 0) {
            $this->record(
                $run,
                $manifest,
                identity: 'duplicate-sets',
                entityType: CatalogDiscrepancyEntityType::Scope,
                type: CatalogDiscrepancyType::DuplicateSet,
                severity: CatalogDiscrepancySeverity::Critical,
                message: sprintf('The scope contains %d duplicate canonical sets.', $measurement->duplicateSetCount),
            );
        }
        if ($measurement->duplicateCardCount > 0) {
            $this->record(
                $run,
                $manifest,
                identity: 'duplicate-cards',
                entityType: CatalogDiscrepancyEntityType::Scope,
                type: CatalogDiscrepancyType::DuplicateCard,
                severity: CatalogDiscrepancySeverity::Critical,
                message: sprintf('The scope contains %d duplicate canonical cards.', $measurement->duplicateCardCount),
            );
        }
        if ($measurement->orphanCardCount > 0) {
            $this->record(
                $run,
                $manifest,
                identity: 'orphan-cards',
                entityType: CatalogDiscrepancyEntityType::Scope,
                type: CatalogDiscrepancyType::OrphanCard,
                severity: CatalogDiscrepancySeverity::Critical,
                message: sprintf('The catalog contains %d cards without a set.', $measurement->orphanCardCount),
            );
        }
    }

    private function record(
        CatalogImportRun $run,
        CatalogCoverageManifest $manifest,
        string $identity,
        CatalogDiscrepancyEntityType $entityType,
        CatalogDiscrepancyType $type,
        CatalogDiscrepancySeverity $severity,
        string $message,
        ?string $externalReference = null,
    ): void {
        $this->discrepancyRecorder->record($run, new CatalogDiscrepancyObservation(
            identity: $identity,
            entityType: $entityType,
            type: $type,
            severity: $severity,
            message: $message,
            externalReference: $externalReference,
            language: $manifest->language,
            region: $manifest->region,
        ));
    }
}
