<?php

declare(strict_types=1);

namespace App\Storage\Domain\Repository;

use App\Collection\Domain\Entity\CollectionItem;
use App\Identity\Domain\Entity\User;
use App\Storage\Domain\Entity\CollectionItemLocationHistory;
use App\Storage\Domain\Entity\StorageLocation;

interface CollectionItemLocationRepository
{
    public function currentForItem(CollectionItem $item): ?CollectionItemLocationHistory;

    /** @return list<CollectionItemLocationHistory> */
    public function historyForItem(CollectionItem $item): array;

    public function place(CollectionItem $item, StorageLocation $location): CollectionItemLocationHistory;

    public function removeCurrent(CollectionItem $item): void;

    /** @return array<string, int> */
    public function currentItemCounts(User $owner): array;
}
