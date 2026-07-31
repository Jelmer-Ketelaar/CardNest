<?php

declare(strict_types=1);

namespace App\Storage\Domain\Entity;

use App\Binder\Domain\Entity\BinderSlot;
use App\Collection\Domain\Entity\CollectionItem;
use App\Storage\Domain\Exception\InvalidStorageTarget;
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
#[ORM\Index(name: 'idx_location_history_binder_slot_current', columns: ['binder_slot_id', 'removed_at'])]
#[ORM\UniqueConstraint(
    name: 'uniq_collection_item_current_location',
    columns: ['collection_item_id'],
    options: ['where' => '(removed_at IS NULL)'],
)]
#[ORM\UniqueConstraint(
    name: 'uniq_binder_slot_current_item',
    columns: ['binder_slot_id'],
    options: ['where' => '((removed_at IS NULL) AND (binder_slot_id IS NOT NULL))'],
)]
final class CollectionItemLocationHistory
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\Column(name: 'removed_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $removedAt = null;

    private function __construct(
        #[ORM\ManyToOne(targetEntity: CollectionItem::class)]
        #[ORM\JoinColumn(name: 'collection_item_id', nullable: false, onDelete: 'CASCADE')]
        private readonly CollectionItem $collectionItem,
        #[ORM\ManyToOne(targetEntity: StorageLocation::class)]
        #[ORM\JoinColumn(name: 'storage_location_id', nullable: true, onDelete: 'RESTRICT')]
        private readonly ?StorageLocation $storageLocation,
        #[ORM\ManyToOne(targetEntity: BinderSlot::class)]
        #[ORM\JoinColumn(name: 'binder_slot_id', nullable: true, onDelete: 'RESTRICT')]
        private readonly ?BinderSlot $binderSlot,
        #[ORM\Column(name: 'placed_at', type: Types::DATETIME_IMMUTABLE)]
        private readonly DateTimeImmutable $placedAt = new DateTimeImmutable(),
    ) {
        if (($storageLocation === null) === ($binderSlot === null)) {
            throw new InvalidStorageTarget('Een plaatsing moet precies één fysieke bestemming hebben.');
        }

        $targetOwner = $storageLocation?->getOwner() ?? $binderSlot?->getPage()->getBinder()->getOwner();
        if ($targetOwner === null || !$collectionItem->getOwner()->getId()->equals($targetOwner->getId())) {
            throw new StorageOwnerMismatch('Een collectiekaart kan alleen in een eigen opslaglocatie worden geplaatst.');
        }

        $this->id = Uuid::v7();
    }

    public static function inStorage(
        CollectionItem $item,
        StorageLocation $location,
        DateTimeImmutable $placedAt = new DateTimeImmutable(),
    ): self {
        return new self($item, $location, null, $placedAt);
    }

    public static function inBinderSlot(
        CollectionItem $item,
        BinderSlot $slot,
        DateTimeImmutable $placedAt = new DateTimeImmutable(),
    ): self {
        return new self($item, null, $slot, $placedAt);
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getCollectionItem(): CollectionItem
    {
        return $this->collectionItem;
    }

    public function getStorageLocation(): ?StorageLocation
    {
        return $this->storageLocation;
    }

    public function getBinderSlot(): ?BinderSlot
    {
        return $this->binderSlot;
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
