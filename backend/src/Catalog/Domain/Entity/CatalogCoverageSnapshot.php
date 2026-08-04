<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Entity;

use App\Identity\Domain\Entity\User;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use InvalidArgumentException;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'catalog_coverage_snapshots')]
#[ORM\UniqueConstraint(
    name: 'uniq_catalog_coverage_manifest_snapshot',
    columns: ['import_run_id', 'manifest_checksum'],
)]
#[ORM\Index(
    name: 'idx_catalog_coverage_scope_created',
    columns: ['provider', 'region', 'language', 'created_at'],
)]
#[ORM\Index(name: 'idx_catalog_coverage_release_status', columns: ['release_gate_status', 'created_at'])]
final class CatalogCoverageSnapshot
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    /**
     * @param array<string, array{
     *     expected: int|null,
     *     observed: int|null,
     *     coverageBasisPoints: int|null,
     *     status: string
     * }> $dimensions
     * @param list<string> $blockingReasons
     */
    public function __construct(
        #[ORM\ManyToOne(targetEntity: CatalogImportRun::class)]
        #[ORM\JoinColumn(name: 'import_run_id', nullable: false, onDelete: 'RESTRICT')]
        private readonly CatalogImportRun $importRun,
        #[ORM\ManyToOne(targetEntity: User::class)]
        #[ORM\JoinColumn(name: 'approved_by_id', nullable: true, onDelete: 'SET NULL')]
        private ?User $approvedBy,
        #[ORM\Column(name: 'approved_by_identifier', length: 36)]
        private readonly string $approvedByIdentifier,
        #[ORM\Column(length: 100)]
        private readonly string $provider,
        #[ORM\Column(length: 50)]
        private readonly string $region,
        #[ORM\Column(length: 10)]
        private readonly string $language,
        #[ORM\Column(name: 'evidence_source', length: 180)]
        private readonly string $evidenceSource,
        #[ORM\Column(name: 'evidence_version', length: 180)]
        private readonly string $evidenceVersion,
        #[ORM\Column(name: 'evidence_reference', length: 1_024)]
        private readonly string $evidenceReference,
        #[ORM\Column(name: 'data_cutoff', type: Types::DATETIME_IMMUTABLE)]
        private readonly DateTimeImmutable $dataCutoff,
        #[ORM\Column(name: 'manifest_checksum', length: 64)]
        private readonly string $manifestChecksum,
        #[ORM\Column(type: Types::JSONB)]
        private readonly array $dimensions,
        #[ORM\Column(name: 'release_gate_status', length: 40, enumType: CatalogReleaseGateStatus::class)]
        private readonly CatalogReleaseGateStatus $releaseGateStatus,
        #[ORM\Column(name: 'blocking_reasons', type: Types::JSONB)]
        private readonly array $blockingReasons,
        #[ORM\Column(name: 'unresolved_critical_discrepancy_count')]
        private readonly int $unresolvedCriticalDiscrepancyCount,
        #[ORM\Column(name: 'externally_blocked_critical_discrepancy_count')]
        private readonly int $externallyBlockedCriticalDiscrepancyCount,
        #[ORM\Column(name: 'duplicate_set_count')]
        private readonly int $duplicateSetCount,
        #[ORM\Column(name: 'duplicate_card_count')]
        private readonly int $duplicateCardCount,
        #[ORM\Column(name: 'orphan_card_count')]
        private readonly int $orphanCardCount,
        #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
        private readonly DateTimeImmutable $createdAt = new DateTimeImmutable(),
    ) {
        if (!$importRun->getStatus()->isSuccessful()) {
            throw new InvalidArgumentException('Coverage snapshots require a successful catalog import run.');
        }
        if ($importRun->getProvider() !== $provider) {
            throw new InvalidArgumentException('Coverage provider must match the catalog import run.');
        }
        if ($importRun->getSourceVersion() === null) {
            throw new InvalidArgumentException('Coverage snapshots require a versioned catalog import source.');
        }
        $importDataCutoff = $importRun->getDataCutoff();
        if ($importDataCutoff === null || $importDataCutoff->format(DATE_ATOM) !== $dataCutoff->format(DATE_ATOM)) {
            throw new InvalidArgumentException('Coverage data cutoff must match the catalog import run.');
        }
        if ($approvedBy === null || $approvedBy->getId()->toRfc4122() !== $approvedByIdentifier) {
            throw new InvalidArgumentException('Coverage evidence requires a traceable approving administrator.');
        }
        if (!preg_match('/^[a-f0-9]{64}$/', $manifestChecksum)) {
            throw new InvalidArgumentException('The coverage manifest checksum must be a SHA-256 checksum.');
        }
        foreach (
            [
                $unresolvedCriticalDiscrepancyCount,
                $externallyBlockedCriticalDiscrepancyCount,
                $duplicateSetCount,
                $duplicateCardCount,
                $orphanCardCount,
            ] as $count
        ) {
            if ($count < 0) {
                throw new InvalidArgumentException('Coverage snapshot counts cannot be negative.');
            }
        }
        if ($externallyBlockedCriticalDiscrepancyCount > $unresolvedCriticalDiscrepancyCount) {
            throw new InvalidArgumentException('Externally blocked discrepancies must be part of unresolved counts.');
        }

        $this->id = Uuid::v7();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getImportRun(): CatalogImportRun
    {
        return $this->importRun;
    }

    public function getApprovedByIdentifier(): string
    {
        return $this->approvedByIdentifier;
    }

    public function getProvider(): string
    {
        return $this->provider;
    }

    public function getRegion(): string
    {
        return $this->region;
    }

    public function getLanguage(): string
    {
        return $this->language;
    }

    public function getEvidenceSource(): string
    {
        return $this->evidenceSource;
    }

    public function getEvidenceVersion(): string
    {
        return $this->evidenceVersion;
    }

    public function getEvidenceReference(): string
    {
        return $this->evidenceReference;
    }

    public function getDataCutoff(): DateTimeImmutable
    {
        return $this->dataCutoff;
    }

    public function getManifestChecksum(): string
    {
        return $this->manifestChecksum;
    }

    /**
     * @return array<string, array{
     *     expected: int|null,
     *     observed: int|null,
     *     coverageBasisPoints: int|null,
     *     status: string
     * }>
     */
    public function getDimensions(): array
    {
        return $this->dimensions;
    }

    public function getReleaseGateStatus(): CatalogReleaseGateStatus
    {
        return $this->releaseGateStatus;
    }

    /** @return list<string> */
    public function getBlockingReasons(): array
    {
        return $this->blockingReasons;
    }

    public function getUnresolvedCriticalDiscrepancyCount(): int
    {
        return $this->unresolvedCriticalDiscrepancyCount;
    }

    public function getExternallyBlockedCriticalDiscrepancyCount(): int
    {
        return $this->externallyBlockedCriticalDiscrepancyCount;
    }

    public function getDuplicateSetCount(): int
    {
        return $this->duplicateSetCount;
    }

    public function getDuplicateCardCount(): int
    {
        return $this->duplicateCardCount;
    }

    public function getOrphanCardCount(): int
    {
        return $this->orphanCardCount;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
