<?php

declare(strict_types=1);

namespace App\Marketplace\Domain\Repository;

use App\Marketplace\Domain\Entity\Listing;

final readonly class ListingSearchResult
{
    /** @param list<Listing> $items */
    public function __construct(
        public array $items,
        public int $total,
        public int $page,
        public int $perPage,
    ) {
    }
}
