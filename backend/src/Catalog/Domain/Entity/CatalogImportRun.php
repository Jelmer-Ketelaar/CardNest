<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Entity;

use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use InvalidArgumentException;
use LogicException;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'catalog_import_runs')]
#[ORM\Index(
    name: 'idx_catalog_import_runs_scope_started',
    columns: ['provider', 'scope_key', 'started_at'],
)]
#[ORM\Index(
    name: 'idx_catalog_import_runs_source_version',
    columns: ['provider', 'scope_key', 'source_version'],
)]
#[ORM\Index(name: 'idx_catalog_import_runs_status_started', columns: ['status', 'started_at'])]
#[ORM\Index(name: 'idx_catalog_import_runs_duplicate', columns: ['duplicate_of_id'])]
final class CatalogImportRun
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\Column(length: 30, enumType: CatalogImportStatus::class)]
    private CatalogImportStatus $status;

    #[ORM\Column(name: 'content_checksum', length: 64, nullable: true)]
    private ?string $contentChecksum = null;

    #[ORM\Column(name: 'staged_set_count')]
    private int $stagedSetCount = 0;

    #[ORM\Column(name: 'staged_card_count')]
    private int $stagedCardCount = 0;

    #[ORM\Column(name: 'published_set_count')]
    private int $publishedSetCount = 0;

    #[ORM\Column(name: 'published_card_count')]
    private int $publishedCardCount = 0;

    #[ORM\Column(name: 'warning_count')]
    private int $warningCount = 0;

    #[ORM\Column(name: 'error_count')]
    private int $errorCount = 0;

    #[ORM\Column(name: 'failure_reason', type: Types::TEXT, nullable: true)]
    private ?string $failureReason = null;

    #[ORM\ManyToOne(targetEntity: self::class)]
    #[ORM\JoinColumn(name: 'duplicate_of_id', nullable: true, onDelete: 'RESTRICT')]
    private ?self $duplicateOf = null;

    #[ORM\Column(name: 'completed_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $completedAt = null;

    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $updatedAt;

    /** @param array<string, mixed> $dataScope */
    public function __construct(
        #[ORM\Column(length: 100)]
        private readonly string $provider,
        #[ORM\Column(name: 'scope_key', length: 64)]
        private readonly string $scopeKey,
        #[ORM\Column(name: 'data_scope', type: Types::JSONB)]
        private readonly array $dataScope,
        #[ORM\Column(name: 'source_version', length: 180, nullable: true)]
        private readonly ?string $sourceVersion,
        #[ORM\Column(name: 'data_cutoff', type: Types::DATETIME_IMMUTABLE, nullable: true)]
        private readonly ?DateTimeImmutable $dataCutoff,
        #[ORM\Column(name: 'started_at', type: Types::DATETIME_IMMUTABLE)]
        private readonly DateTimeImmutable $startedAt = new DateTimeImmutable(),
    ) {
        $trimmedProvider = trim($provider);
        if (
            $provider !== $trimmedProvider
            || $trimmedProvider === ''
            || mb_strlen($trimmedProvider) > 100
            || preg_match('/[\x00-\x1F\x7F]/u', $provider) === 1
        ) {
            throw new InvalidArgumentException('The catalog import provider must contain 1 to 100 characters.');
        }
        if (!preg_match('/^[a-f0-9]{64}$/', $scopeKey)) {
            throw new InvalidArgumentException('The catalog import scope key must be a SHA-256 checksum.');
        }
        if ($sourceVersion !== null && (
            $sourceVersion !== trim($sourceVersion)
            || $sourceVersion === ''
            || mb_strlen($sourceVersion) > 180
            || preg_match('/[\x00-\x1F\x7F]/u', $sourceVersion) === 1
        )) {
            $message = 'The catalog source version must contain 1 to 180 trimmed characters without controls.';
            throw new InvalidArgumentException($message);
        }

        $this->id = Uuid::v7();
        $this->status = CatalogImportStatus::Running;
        $this->updatedAt = $startedAt;
    }

    public function markValidating(
        string $contentChecksum,
        int $setCount,
        int $cardCount,
        int $warningCount = 0,
        DateTimeImmutable $at = new DateTimeImmutable(),
    ): void {
        $this->assertStatus(CatalogImportStatus::Running);
        if (!preg_match('/^[a-f0-9]{64}$/', $contentChecksum)) {
            throw new InvalidArgumentException('The catalog content checksum must be a SHA-256 checksum.');
        }
        $this->assertCounts($setCount, $cardCount, $warningCount);

        $this->contentChecksum = $contentChecksum;
        $this->stagedSetCount = $setCount;
        $this->stagedCardCount = $cardCount;
        $this->warningCount = $warningCount;
        $this->status = CatalogImportStatus::Validating;
        $this->updatedAt = $at;
    }

    public function recordStagedCounts(
        int $setCount,
        int $cardCount,
        DateTimeImmutable $at = new DateTimeImmutable(),
    ): void {
        $this->assertStatus(CatalogImportStatus::Running);
        $this->assertCounts($setCount, $cardCount);
        $this->stagedSetCount = $setCount;
        $this->stagedCardCount = $cardCount;
        $this->updatedAt = $at;
    }

    public function markPublishing(DateTimeImmutable $at = new DateTimeImmutable()): void
    {
        $this->assertStatus(CatalogImportStatus::Validating);
        $this->status = CatalogImportStatus::Publishing;
        $this->updatedAt = $at;
    }

    public function complete(
        int $publishedSetCount,
        int $publishedCardCount,
        DateTimeImmutable $at = new DateTimeImmutable(),
    ): void {
        $this->assertStatus(CatalogImportStatus::Publishing);
        $this->assertCounts($publishedSetCount, $publishedCardCount, $this->warningCount);
        if ($publishedSetCount !== $this->stagedSetCount || $publishedCardCount !== $this->stagedCardCount) {
            throw new LogicException('A completed catalog import must publish every staged record.');
        }

        $this->publishedSetCount = $publishedSetCount;
        $this->publishedCardCount = $publishedCardCount;
        $this->status = $this->warningCount > 0
            ? CatalogImportStatus::CompletedWithWarnings
            : CatalogImportStatus::Completed;
        $this->completedAt = $at;
        $this->updatedAt = $at;
    }

    public function skipAsDuplicate(self $completedRun, DateTimeImmutable $at = new DateTimeImmutable()): void
    {
        $this->assertStatus(CatalogImportStatus::Validating);
        if (!in_array(
            $completedRun->status,
            [CatalogImportStatus::Completed, CatalogImportStatus::CompletedWithWarnings],
            true,
        )) {
            throw new InvalidArgumentException('Only a completed catalog import can be reused.');
        }
        if (
            $completedRun->provider !== $this->provider
            || $completedRun->scopeKey !== $this->scopeKey
            || $completedRun->sourceVersion !== $this->sourceVersion
            || $completedRun->contentChecksum !== $this->contentChecksum
        ) {
            throw new InvalidArgumentException('A replay must match the completed catalog source fingerprint.');
        }

        $this->duplicateOf = $completedRun;
        $this->status = CatalogImportStatus::Skipped;
        $this->completedAt = $at;
        $this->updatedAt = $at;
    }

    public function fail(string $reason, DateTimeImmutable $at = new DateTimeImmutable()): void
    {
        if ($this->status->isTerminal()) {
            throw new LogicException('A terminal catalog import run cannot fail again.');
        }

        $normalizedReason = trim($reason);
        $this->failureReason = mb_substr(
            $normalizedReason !== '' ? $normalizedReason : 'Catalog import failed without a safe error summary.',
            0,
            1_000,
        );
        ++$this->errorCount;
        $this->status = CatalogImportStatus::Failed;
        $this->completedAt = $at;
        $this->updatedAt = $at;
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getProvider(): string
    {
        return $this->provider;
    }

    public function getScopeKey(): string
    {
        return $this->scopeKey;
    }

    /** @return array<string, mixed> */
    public function getDataScope(): array
    {
        return $this->dataScope;
    }

    public function getSourceVersion(): ?string
    {
        return $this->sourceVersion;
    }

    public function getDataCutoff(): ?DateTimeImmutable
    {
        return $this->dataCutoff;
    }

    public function getStatus(): CatalogImportStatus
    {
        return $this->status;
    }

    public function getContentChecksum(): ?string
    {
        return $this->contentChecksum;
    }

    public function getStagedSetCount(): int
    {
        return $this->stagedSetCount;
    }

    public function getStagedCardCount(): int
    {
        return $this->stagedCardCount;
    }

    public function getPublishedSetCount(): int
    {
        return $this->publishedSetCount;
    }

    public function getPublishedCardCount(): int
    {
        return $this->publishedCardCount;
    }

    public function getWarningCount(): int
    {
        return $this->warningCount;
    }

    public function getErrorCount(): int
    {
        return $this->errorCount;
    }

    public function getFailureReason(): ?string
    {
        return $this->failureReason;
    }

    public function getDuplicateOf(): ?self
    {
        return $this->duplicateOf;
    }

    public function getStartedAt(): DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function getCompletedAt(): ?DateTimeImmutable
    {
        return $this->completedAt;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    private function assertStatus(CatalogImportStatus $expected): void
    {
        if ($this->status !== $expected) {
            $message = sprintf(
                'Catalog import status "%s" cannot transition as "%s".',
                $this->status->value,
                $expected->value,
            );
            throw new LogicException($message);
        }
    }

    private function assertCounts(int ...$counts): void
    {
        if (array_any($counts, static fn (int $count): bool => $count < 0)) {
            throw new InvalidArgumentException('Catalog import counts cannot be negative.');
        }
    }
}
