<?php

declare(strict_types=1);

namespace App\Storage\Infrastructure\Persistence;

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
        return $this->findCurrent($item, false);
    }

    public function historyForItem(CollectionItem $item): array
    {
        /** @var list<CollectionItemLocationHistory> $history */
        $history = $this->entityManager->createQueryBuilder()
            ->select('h', 'l')
            ->from(CollectionItemLocationHistory::class, 'h')
            ->join('h.storageLocation', 'l')
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
            $current = $this->findCurrent($item, true);
            if ($current instanceof CollectionItemLocationHistory
                && $current->getStorageLocation()->getId()->equals($location->getId())) {
                return $current;
            }

            $now = new DateTimeImmutable();
            $current?->remove($now);
            if ($current instanceof CollectionItemLocationHistory) {
                // PostgreSQL checks the partial unique index during INSERT. Flush the closed
                // history row first while both writes remain in the same transaction.
                $this->entityManager->flush();
            }
            $history = new CollectionItemLocationHistory($item, $location, $now);
            $this->entityManager->persist($history);

            return $history;
        });
    }

    public function removeCurrent(CollectionItem $item): void
    {
        $this->entityManager->wrapInTransaction(function () use ($item): void {
            $this->entityManager->lock($item, LockMode::PESSIMISTIC_WRITE);
            $this->findCurrent($item, true)?->remove();
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

    private function findCurrent(CollectionItem $item, bool $forUpdate): ?CollectionItemLocationHistory
    {
        $query = $this->entityManager->createQueryBuilder()
            ->select('h', 'l')
            ->from(CollectionItemLocationHistory::class, 'h')
            ->join('h.storageLocation', 'l')
            ->where('h.collectionItem = :item')
            ->andWhere('h.removedAt IS NULL')
            ->setParameter('item', $item)
            ->getQuery();

        if ($forUpdate) {
            $query->setLockMode(LockMode::PESSIMISTIC_WRITE);
        }

        /** @var CollectionItemLocationHistory|null $history */
        $history = $query->getOneOrNullResult();

        return $history;
    }
}
