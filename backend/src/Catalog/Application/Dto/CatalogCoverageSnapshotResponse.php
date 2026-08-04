<?php

declare(strict_types=1);

namespace App\Catalog\Application\Dto;

use App\Catalog\Domain\Entity\CatalogCoverageDimension;
use App\Catalog\Domain\Entity\CatalogCoverageSnapshot;
use App\Catalog\Domain\Entity\CatalogReleaseGateStatus;

final readonly class CatalogCoverageSnapshotResponse
{
    public function __construct(private CatalogCoverageSnapshot $snapshot)
    {
    }

    /** @return array<string, mixed> */
    public function toArray(bool $includeEvidenceReference = false): array
    {
        $evidence = [
            'source' => $this->snapshot->getEvidenceSource(),
            'version' => $this->snapshot->getEvidenceVersion(),
            'manifestChecksum' => $this->snapshot->getManifestChecksum(),
            'importRunId' => $this->snapshot->getImportRun()->getId()->toRfc4122(),
            'sourceVersion' => $this->snapshot->getImportRun()->getSourceVersion(),
            'sourceDataCutoff' => $this->snapshot->getImportRun()->getDataCutoff()?->format(DATE_ATOM),
        ];
        if ($includeEvidenceReference) {
            $evidence['reference'] = $this->snapshot->getEvidenceReference();
        }

        return [
            'id' => $this->snapshot->getId()->toRfc4122(),
            'scope' => [
                'provider' => $this->snapshot->getProvider(),
                'region' => $this->snapshot->getRegion(),
                'language' => $this->snapshot->getLanguage(),
                'dataCutoff' => $this->snapshot->getDataCutoff()->format(DATE_ATOM),
            ],
            'evidence' => $evidence,
            'dimensions' => $this->snapshot->getDimensions(),
            'discrepancies' => [
                'unresolvedCritical' => $this->snapshot->getUnresolvedCriticalDiscrepancyCount(),
                'blockedExternalCritical' => $this->snapshot->getExternallyBlockedCriticalDiscrepancyCount(),
                'duplicateSets' => $this->snapshot->getDuplicateSetCount(),
                'duplicateCards' => $this->snapshot->getDuplicateCardCount(),
                'orphanCards' => $this->snapshot->getOrphanCardCount(),
            ],
            'releaseGate' => [
                'status' => $this->snapshot->getReleaseGateStatus()->value,
                'passed' => $this->snapshot->getReleaseGateStatus() === CatalogReleaseGateStatus::Passed,
                'blockingReasons' => $this->snapshot->getBlockingReasons(),
            ],
            'createdAt' => $this->snapshot->getCreatedAt()->format(DATE_ATOM),
        ];
    }

    /** @return array<string, mixed> */
    public static function notVerified(string $provider, string $region, string $language): array
    {
        $dimensions = [];
        foreach (CatalogCoverageDimension::cases() as $dimension) {
            $dimensions[$dimension->value] = [
                'expected' => null,
                'observed' => null,
                'coverageBasisPoints' => null,
                'status' => 'not_verified',
            ];
        }

        return [
            'id' => null,
            'scope' => [
                'provider' => $provider,
                'region' => $region,
                'language' => $language,
                'dataCutoff' => null,
            ],
            'evidence' => null,
            'dimensions' => $dimensions,
            'discrepancies' => null,
            'releaseGate' => [
                'status' => 'not_verified',
                'passed' => false,
                'blockingReasons' => ['no_verified_snapshot'],
            ],
            'createdAt' => null,
        ];
    }
}
