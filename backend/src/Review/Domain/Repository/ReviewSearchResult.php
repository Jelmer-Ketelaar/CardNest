<?php

declare(strict_types=1);

namespace App\Review\Domain\Repository;

use App\Review\Domain\Entity\Review;

final readonly class ReviewSearchResult
{
    /** @param list<Review> $items */
    public function __construct(
        public array $items,
        public int $total,
        public int $page,
        public int $perPage,
    ) {
    }
}
