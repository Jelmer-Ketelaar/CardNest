<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Entity;

use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Traces a CatalogCard back to the external source it was imported from. Import
 * adapters are not implemented yet (see product spec section 37), but the schema
 * is ready so a future CardCatalogProvider can populate and resynchronize it
 * without overwriting manual corrections.
 */
#[ORM\Entity]
#[ORM\Table(name: 'card_external_references')]
#[ORM\UniqueConstraint(name: 'uniq_card_ext_ref_source', columns: ['source_name', 'external_id'])]
final class CardExternalReference
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private readonly Uuid $id;

    /** @var array<string, mixed>|null */
    #[ORM\Column(name: 'corrected_data', type: Types::JSONB, nullable: true)]
    private ?array $correctedData = null;

    #[ORM\Column(name: 'last_synced_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $lastSyncedAt = null;

    private DateTimeImmutable $updatedAt;

    /** @param array<string, mixed> $rawData */
    public function __construct(
        #[ORM\ManyToOne(targetEntity: CatalogCard::class)]
        #[ORM\JoinColumn(name: 'catalog_card_id', nullable: false, onDelete: 'CASCADE')]
        private readonly CatalogCard $catalogCard,
        #[ORM\Column(name: 'source_name', length: 100)]
        private readonly string $sourceName,
        #[ORM\Column(name: 'external_id', length: 180)]
        private readonly string $externalId,
        #[ORM\Column(type: Types::JSONB)]
        private array $rawData = [],
        #[ORM\Column(name: 'imported_at', type: Types::DATETIME_IMMUTABLE)]
        private readonly DateTimeImmutable $importedAt = new DateTimeImmutable(),
        #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
        private readonly DateTimeImmutable $createdAt = new DateTimeImmutable(),
    ) {
        $this->id = Uuid::v7();
        $this->updatedAt = $createdAt;
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getCatalogCard(): CatalogCard
    {
        return $this->catalogCard;
    }

    public function getSourceName(): string
    {
        return $this->sourceName;
    }

    public function getExternalId(): string
    {
        return $this->externalId;
    }

    /** @return array<string, mixed> */
    public function getRawData(): array
    {
        return $this->rawData;
    }

    /** @return array<string, mixed>|null */
    public function getCorrectedData(): ?array
    {
        return $this->correctedData;
    }

    /**
     * Applies manually-corrected fields that a future re-sync must not overwrite
     * without explicit review.
     *
     * @param array<string, mixed> $correctedData
     */
    public function applyManualCorrection(array $correctedData): void
    {
        $this->correctedData = $correctedData;
        $this->updatedAt = new DateTimeImmutable();
    }

    /** @param array<string, mixed> $rawData */
    public function markSynced(array $rawData, DateTimeImmutable $syncedAt = new DateTimeImmutable()): void
    {
        $this->rawData = $rawData;
        $this->lastSyncedAt = $syncedAt;
        $this->updatedAt = $syncedAt;
    }

    public function getImportedAt(): DateTimeImmutable
    {
        return $this->importedAt;
    }

    public function getLastSyncedAt(): ?DateTimeImmutable
    {
        return $this->lastSyncedAt;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
