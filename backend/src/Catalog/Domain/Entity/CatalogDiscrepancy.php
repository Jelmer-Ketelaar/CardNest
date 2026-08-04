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
#[ORM\Table(name: 'catalog_discrepancies')]
#[ORM\UniqueConstraint(name: 'uniq_catalog_discrepancy_fingerprint', columns: ['fingerprint'])]
#[ORM\Index(
    name: 'idx_catalog_discrepancies_scope_status',
    columns: ['provider', 'scope_key', 'language', 'region', 'resolution_status', 'severity'],
)]
#[ORM\Index(name: 'idx_catalog_discrepancies_last_seen', columns: ['last_seen_at'])]
#[ORM\Index(name: 'idx_catalog_discrepancies_import_run', columns: ['last_import_run_id'])]
final class CatalogDiscrepancy
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\Column(length: 30, enumType: CatalogDiscrepancySeverity::class)]
    private CatalogDiscrepancySeverity $severity;

    #[ORM\Column(type: Types::TEXT)]
    private string $message;

    #[ORM\Column(name: 'resolution_status', length: 30, enumType: CatalogDiscrepancyResolutionStatus::class)]
    private CatalogDiscrepancyResolutionStatus $resolutionStatus;

    #[ORM\Column(name: 'occurrence_count')]
    private int $occurrenceCount = 1;

    #[ORM\Column(name: 'resolution_reason', type: Types::TEXT, nullable: true)]
    private ?string $resolutionReason = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'resolved_by_id', nullable: true, onDelete: 'SET NULL')]
    private ?User $resolvedBy = null;

    #[ORM\Column(name: 'resolved_by_identifier', length: 36, nullable: true)]
    private ?string $resolvedByIdentifier = null;

    #[ORM\Column(name: 'resolved_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $resolvedAt = null;

    /**
     * @var list<array{
     *     from: string,
     *     to: string,
     *     reason: string,
     *     actorId: string|null,
     *     changedAt: string
     * }>
     */
    #[ORM\Column(name: 'resolution_history', type: Types::JSONB)]
    private array $resolutionHistory = [];

    #[ORM\Column(name: 'last_seen_at', type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $lastSeenAt;

    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $updatedAt;

    public function __construct(
        #[ORM\ManyToOne(targetEntity: CatalogImportRun::class)]
        #[ORM\JoinColumn(name: 'last_import_run_id', nullable: false, onDelete: 'RESTRICT')]
        private CatalogImportRun $lastImportRun,
        #[ORM\Column(length: 100)]
        private readonly string $provider,
        #[ORM\Column(name: 'scope_key', length: 64)]
        private readonly string $scopeKey,
        #[ORM\Column(length: 10, nullable: true)]
        private readonly ?string $language,
        #[ORM\Column(length: 50, nullable: true)]
        private readonly ?string $region,
        #[ORM\Column(name: 'entity_type', length: 30, enumType: CatalogDiscrepancyEntityType::class)]
        private readonly CatalogDiscrepancyEntityType $entityType,
        #[ORM\Column(name: 'external_reference', length: 500, nullable: true)]
        private readonly ?string $externalReference,
        #[ORM\Column(name: 'discrepancy_type', length: 50, enumType: CatalogDiscrepancyType::class)]
        private readonly CatalogDiscrepancyType $discrepancyType,
        CatalogDiscrepancySeverity $severity,
        string $message,
        #[ORM\Column(length: 64)]
        private readonly string $fingerprint,
        #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
        private readonly DateTimeImmutable $createdAt = new DateTimeImmutable(),
    ) {
        $this->assertIdentifier($provider, 100, 'provider');
        $this->assertOptionalIdentifier($language, 10, 'language');
        $this->assertOptionalIdentifier($region, 50, 'region');
        $this->assertOptionalIdentifier($externalReference, 500, 'external reference');
        if (!preg_match('/^[a-f0-9]{64}$/', $scopeKey)) {
            throw new InvalidArgumentException('The discrepancy scope key must be a SHA-256 checksum.');
        }
        if (!preg_match('/^[a-f0-9]{64}$/', $fingerprint)) {
            throw new InvalidArgumentException('The discrepancy fingerprint must be a SHA-256 checksum.');
        }

        $this->id = Uuid::v7();
        $this->severity = $severity;
        $this->message = $this->normalizeMessage($message);
        $this->resolutionStatus = CatalogDiscrepancyResolutionStatus::Open;
        $this->lastSeenAt = $createdAt;
        $this->updatedAt = $createdAt;
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getLastImportRun(): CatalogImportRun
    {
        return $this->lastImportRun;
    }

    public function getProvider(): string
    {
        return $this->provider;
    }

    public function getScopeKey(): string
    {
        return $this->scopeKey;
    }

    public function getLanguage(): ?string
    {
        return $this->language;
    }

    public function getRegion(): ?string
    {
        return $this->region;
    }

    public function getEntityType(): CatalogDiscrepancyEntityType
    {
        return $this->entityType;
    }

    public function getExternalReference(): ?string
    {
        return $this->externalReference;
    }

    public function getDiscrepancyType(): CatalogDiscrepancyType
    {
        return $this->discrepancyType;
    }

    public function getSeverity(): CatalogDiscrepancySeverity
    {
        return $this->severity;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getFingerprint(): string
    {
        return $this->fingerprint;
    }

    public function getResolutionStatus(): CatalogDiscrepancyResolutionStatus
    {
        return $this->resolutionStatus;
    }

    public function getOccurrenceCount(): int
    {
        return $this->occurrenceCount;
    }

    public function getResolutionReason(): ?string
    {
        return $this->resolutionReason;
    }

    public function getResolvedByIdentifier(): ?string
    {
        return $this->resolvedByIdentifier;
    }

    public function getResolvedAt(): ?DateTimeImmutable
    {
        return $this->resolvedAt;
    }

    /**
     * @return list<array{
     *     from: string,
     *     to: string,
     *     reason: string,
     *     actorId: string|null,
     *     changedAt: string
     * }>
     */
    public function getResolutionHistory(): array
    {
        return $this->resolutionHistory;
    }

    public function getLastSeenAt(): DateTimeImmutable
    {
        return $this->lastSeenAt;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function observe(
        CatalogImportRun $importRun,
        CatalogDiscrepancySeverity $severity,
        string $message,
        DateTimeImmutable $observedAt = new DateTimeImmutable(),
    ): bool {
        if ($this->lastImportRun->getId()->equals($importRun->getId())) {
            return false;
        }

        $this->lastImportRun = $importRun;
        ++$this->occurrenceCount;
        if ($severity->rank() > $this->severity->rank()) {
            $this->severity = $severity;
        }
        $this->message = $this->normalizeMessage($message);
        $this->lastSeenAt = $observedAt;
        $this->updatedAt = $observedAt;

        if ($this->resolutionStatus === CatalogDiscrepancyResolutionStatus::Resolved) {
            $this->appendResolutionHistory(
                CatalogDiscrepancyResolutionStatus::Open,
                'Automatically reopened because the discrepancy recurred in a later import.',
                null,
                $observedAt,
            );
            $this->resolutionStatus = CatalogDiscrepancyResolutionStatus::Open;
            $this->resolutionReason = null;
            $this->resolvedBy = null;
            $this->resolvedByIdentifier = null;
            $this->resolvedAt = null;
        }

        return true;
    }

    public function resolve(
        CatalogDiscrepancyResolutionStatus $status,
        string $reason,
        User $actor,
        DateTimeImmutable $resolvedAt = new DateTimeImmutable(),
    ): bool {
        if ($status === CatalogDiscrepancyResolutionStatus::Open) {
            throw new InvalidArgumentException('Use a recurrence to reopen a catalog discrepancy.');
        }

        $normalizedReason = trim($reason);
        if (
            mb_strlen($normalizedReason) < 10
            || mb_strlen($normalizedReason) > 1_000
            || preg_match('/[\x00-\x1F\x7F]/u', $normalizedReason) === 1
        ) {
            throw new InvalidArgumentException('A discrepancy resolution reason must contain 10 to 1000 characters.');
        }

        $actorIdentifier = $actor->getId()->toRfc4122();
        if (
            $this->resolutionStatus === $status
            && $this->resolutionReason === $normalizedReason
            && $this->resolvedByIdentifier === $actorIdentifier
        ) {
            return false;
        }

        $this->appendResolutionHistory($status, $normalizedReason, $actorIdentifier, $resolvedAt);
        $this->resolutionStatus = $status;
        $this->resolutionReason = $normalizedReason;
        $this->resolvedBy = $actor;
        $this->resolvedByIdentifier = $actorIdentifier;
        $this->resolvedAt = $resolvedAt;
        $this->updatedAt = $resolvedAt;

        return true;
    }

    private function appendResolutionHistory(
        CatalogDiscrepancyResolutionStatus $status,
        string $reason,
        ?string $actorIdentifier,
        DateTimeImmutable $changedAt,
    ): void {
        $this->resolutionHistory[] = [
            'from' => $this->resolutionStatus->value,
            'to' => $status->value,
            'reason' => $reason,
            'actorId' => $actorIdentifier,
            'changedAt' => $changedAt->format(DATE_ATOM),
        ];
    }

    private function normalizeMessage(string $message): string
    {
        $normalized = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', trim($message));
        $safeMessage = is_string($normalized) ? trim($normalized) : '';
        if ($safeMessage === '') {
            throw new InvalidArgumentException('A catalog discrepancy message is required.');
        }

        return mb_substr($safeMessage, 0, 2_000);
    }

    private function assertIdentifier(string $value, int $maxLength, string $field): void
    {
        if (
            $value !== trim($value)
            || $value === ''
            || mb_strlen($value) > $maxLength
            || preg_match('/[\x00-\x1F\x7F]/u', $value) === 1
        ) {
            throw new InvalidArgumentException(sprintf('The discrepancy %s is invalid.', $field));
        }
    }

    private function assertOptionalIdentifier(?string $value, int $maxLength, string $field): void
    {
        if ($value === null) {
            return;
        }

        $this->assertIdentifier($value, $maxLength, $field);
    }
}
