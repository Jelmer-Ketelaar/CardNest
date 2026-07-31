<?php

declare(strict_types=1);

namespace App\Storage\Domain\Entity;

use App\Collection\Domain\Entity\CollectionItem;
use App\Storage\Domain\Exception\StorageOwnerMismatch;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'collection_item_location_history')]
#[ORM\Index(name: 'idx_location_history_item_time', columns: ['collection_item_id', 'placed_at'])]
#[ORM\Index(name: 'idx_location_history_location_current', columns: ['storage_location_id', 'removed_at'])]
#[ORM\UniqueConstraint(
    name: 'uniq_collection_item_current_location',
    columns: ['collection_item_id'],
    options: ['where' => '(removed_at IS NULL)'],
)]
final class CollectionItemLocationHistory
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\Column(name: 'removed_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $removedAt = null;

    public function __construct(
        #[ORM\ManyToOne(targetEntity: CollectionItem::class)]
        #[ORM\JoinColumn(name: 'collection_item_id', nullable: false, onDelete: 'CASCADE')]
        private readonly CollectionItem $collectionItem,
        #[ORM\ManyToOne(targetEntity: StorageLocation::class)]
        #[ORM\JoinColumn(name: 'storage_location_id', nullable: false, onDelete: 'RESTRICT')]
        private readonly StorageLocation $storageLocation,
        #[ORM\Column(name: 'placed_at', type: Types::DATETIME_IMMUTABLE)]
        private readonly DateTimeImmutable $placedAt = new DateTimeImmutable(),
    ) {
        if (!$collectionItem->getOwner()->getId()->equals($storageLocation->getOwner()->getId())) {
            throw new StorageOwnerMismatch('Een collectiekaart kan alleen in een eigen opslaglocatie worden geplaatst.');
        }

        $this->id = Uuid::v7();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getCollectionItem(): CollectionItem
    {
        return $this->collectionItem;
    }

    public function getStorageLocation(): StorageLocation
    {
        return $this->storageLocation;
    }

    public function getPlacedAt(): DateTimeImmutable
    {
        return $this->placedAt;
    }

    public function getRemovedAt(): ?DateTimeImmutable
    {
        return $this->removedAt;
    }

    public function isCurrent(): bool
    {
        return $this->removedAt === null;
    }

    public function remove(DateTimeImmutable $removedAt = new DateTimeImmutable()): void
    {
        if ($this->removedAt !== null) {
            return;
        }

        $this->removedAt = $removedAt;
    }
}
