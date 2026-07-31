<?php

declare(strict_types=1);

namespace App\Review\Application\Dto;

use App\Review\Domain\Entity\Review;

final readonly class ReviewCollectionResponse
{
    /** @param list<Review> $reviews */
    public function __construct(
        private array $reviews,
        private bool $includeOrder,
    ) {
    }

    /** @return array{items: list<array<string, mixed>>, total: int} */
    public function toArray(): array
    {
        return [
            'items' => array_map(
                fn (Review $review): array => (new ReviewResponse($review, $this->includeOrder))->toArray(),
                $this->reviews,
            ),
            'total' => count($this->reviews),
        ];
    }
}
