<?php

declare(strict_types=1);

namespace App\Collection\Domain\Repository;

use App\Collection\Domain\Entity\CollectionItem;
use App\Identity\Domain\Entity\User;

interface CollectionItemRepository
{
    public function search(User $owner, CollectionItemSearchCriteria $criteria): CollectionItemSearchResult;

    public function getForOwner(string $id, User $owner): ?CollectionItem;

    /** @param list<CollectionItem> $items */
    public function saveMany(array $items): void;

    public function save(CollectionItem $item): void;

    /** @return array<string, int> */
    public function duplicateCounts(User $owner): array;
}
