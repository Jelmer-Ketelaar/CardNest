<?php

declare(strict_types=1);

namespace App\Storage\Infrastructure\Persistence;

use App\Binder\Domain\Entity\Binder;
use App\Binder\Domain\Entity\BinderSlot;
use App\Binder\Domain\Exception\BinderSlotOccupied;
use App\Collection\Domain\Entity\CollectionItem;
use App\Identity\Domain\Entity\User;
use App\Storage\Domain\Entity\CollectionItemLocationHistory;
use App\Storage\Domain\Entity\StorageLocation;
use App\Storage\Domain\Repository\CollectionItemLocationRepository;
use DateTimeImmutable;
use Doctrine\DBAL\LockMode;
use Doctrine\DBAL\ParameterType;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineCollectionItemLocationRepository implements CollectionItemLocationRepository
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function currentForItem(CollectionItem $item): ?CollectionItemLocationHistory
    {
        return $this->findCurrent($item);
    }

    public function historyForItem(CollectionItem $item): array
    {
        /** @var list<CollectionItemLocationHistory> $history */
        $history = $this->entityManager->createQueryBuilder()
            ->select('h', 'l', 'bs', 'bp', 'b')
            ->from(CollectionItemLocationHistory::class, 'h')
            ->leftJoin('h.storageLocation', 'l')
            ->leftJoin('h.binderSlot', 'bs')
            ->leftJoin('bs.page', 'bp')
            ->leftJoin('bp.binder', 'b')
            ->where('h.collectionItem = :item')
            ->setParameter('item', $item)
            ->orderBy('h.placedAt', 'DESC')
            ->addOrderBy('h.id', 'DESC')
            ->getQuery()
            ->getResult();

        return $history;
    }

    public function place(CollectionItem $item, StorageLocation $location): CollectionItemLocationHistory
    {
        return $this->entityManager->wrapInTransaction(function () use ($item, $location): CollectionItemLocationHistory {
            $this->entityManager->lock($item, LockMode::PESSIMISTIC_WRITE);
            $current = $this->findCurrent($item);
            if ($current?->getStorageLocation()?->getId()->equals($location->getId())) {
                return $current;
            }

            $now = new DateTimeImmutable();
            $this->closeCurrent($current, $now);
            $history = CollectionItemLocationHistory::inStorage($item, $location, $now);
            $this->entityManager->persist($history);

            return $history;
        });
    }

    public function placeInBinderSlot(CollectionItem $item, BinderSlot $slot): CollectionItemLocationHistory
    {
        return $this->entityManager->wrapInTransaction(function () use ($item, $slot): CollectionItemLocationHistory {
            $this->entityManager->lock($item, LockMode::PESSIMISTIC_WRITE);
            $this->entityManager->lock($slot, LockMode::PESSIMISTIC_WRITE);
            $target = $this->findCurrentForBinderSlot($slot);
            if ($target instanceof CollectionItemLocationHistory
                && !$target->getCollectionItem()->getId()->equals($item->getId())) {
                throw new BinderSlotOccupied('Dit bindervak bevat al een andere fysieke kaart.');
            }

            $current = $this->findCurrent($item);
            if ($current?->getBinderSlot()?->getId()->equals($slot->getId())) {
                return $current;
            }

            $now = new DateTimeImmutable();
            $this->closeCurrent($current, $now);
            $history = CollectionItemLocationHistory::inBinderSlot($item, $slot, $now);
            $this->entityManager->persist($history);

            return $history;
        });
    }

    public function currentForBinderSlot(BinderSlot $slot): ?CollectionItemLocationHistory
    {
        return $this->findCurrentForBinderSlot($slot);
    }

    public function currentItemsForBinder(Binder $binder): array
    {
        /** @var list<CollectionItemLocationHistory> $placements */
        $placements = $this->entityManager->createQueryBuilder()
            ->select('h', 'i', 'c', 's', 'v', 'bs', 'bp')
            ->from(CollectionItemLocationHistory::class, 'h')
            ->join('h.collectionItem', 'i')
            ->join('i.catalogCard', 'c')
            ->join('c.set', 's')
            ->join('i.variant', 'v')
            ->join('h.binderSlot', 'bs')
            ->join('bs.page', 'bp')
            ->where('bp.binder = :binder')
            ->andWhere('h.removedAt IS NULL')
            ->setParameter('binder', $binder)
            ->getQuery()
            ->getResult();

        $items = [];
        foreach ($placements as $placement) {
            $slot = $placement->getBinderSlot();
            if ($slot instanceof BinderSlot) {
                $items[$slot->getId()->toRfc4122()] = $placement->getCollectionItem();
            }
        }

        return $items;
    }

    public function removeCurrent(CollectionItem $item): void
    {
        $this->entityManager->wrapInTransaction(function () use ($item): void {
            $this->entityManager->lock($item, LockMode::PESSIMISTIC_WRITE);
            $this->findCurrent($item)?->remove();
        });
    }

    public function currentItemCounts(User $owner): array
    {
        /** @var list<array{location_id: string, item_count: int|string}> $rows */
        $rows = $this->entityManager->getConnection()->fetchAllAssociative(
            <<<'SQL'
                SELECT history.storage_location_id::text AS location_id, COUNT(*) AS item_count
                FROM collection_item_location_history history
                INNER JOIN storage_locations location ON location.id = history.storage_location_id
                WHERE location.owner_id = :owner_id AND history.removed_at IS NULL
                GROUP BY history.storage_location_id
                SQL,
            ['owner_id' => $owner->getId()->toRfc4122()],
            ['owner_id' => ParameterType::STRING],
        );

        $counts = [];
        foreach ($rows as $row) {
            $counts[$row['location_id']] = (int) $row['item_count'];
        }

        return $counts;
    }

    private function findCurrent(CollectionItem $item): ?CollectionItemLocationHistory
    {
        $query = $this->entityManager->createQueryBuilder()
            ->select('h', 'l', 'bs')
            ->from(CollectionItemLocationHistory::class, 'h')
            ->leftJoin('h.storageLocation', 'l')
            ->leftJoin('h.binderSlot', 'bs')
            ->where('h.collectionItem = :item')
            ->andWhere('h.removedAt IS NULL')
            ->setParameter('item', $item)
            ->getQuery();

        /** @var CollectionItemLocationHistory|null $history */
        $history = $query->getOneOrNullResult();

        return $history;
    }

    private function findCurrentForBinderSlot(BinderSlot $slot): ?CollectionItemLocationHistory
    {
        $query = $this->entityManager->createQueryBuilder()
            ->select('h', 'i')
            ->from(CollectionItemLocationHistory::class, 'h')
            ->join('h.collectionItem', 'i')
            ->where('h.binderSlot = :slot')
            ->andWhere('h.removedAt IS NULL')
            ->setParameter('slot', $slot)
            ->getQuery();

        /** @var CollectionItemLocationHistory|null $history */
        $history = $query->getOneOrNullResult();

        return $history;
    }

    private function closeCurrent(?CollectionItemLocationHistory $current, DateTimeImmutable $removedAt): void
    {
        if (!$current instanceof CollectionItemLocationHistory) {
            return;
        }

        $current->remove($removedAt);
        // PostgreSQL checks partial unique indexes during INSERT. Flush the closed
        // history row first while both writes remain in the same transaction.
        $this->entityManager->flush();
    }
}
