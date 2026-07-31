<?php

declare(strict_types=1);

namespace App\Collection\Domain\Repository;

use App\Collection\Domain\Entity\CollectionItem;

final readonly class CollectionItemSearchResult
{
    /** @param list<CollectionItem> $items */
    public function __construct(
        public array $items,
        public int $total,
        public int $page,
        public int $perPage,
    ) {
    }
}
