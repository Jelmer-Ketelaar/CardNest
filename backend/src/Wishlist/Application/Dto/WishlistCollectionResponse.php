<?php

declare(strict_types=1);

namespace App\Wishlist\Application\Dto;

use App\Wishlist\Domain\Entity\Wishlist;

final readonly class WishlistCollectionResponse
{
    /** @param list<Wishlist> $wishlists */
    public function __construct(private array $wishlists)
    {
    }

    /** @return array{items: list<array<string, mixed>>, total: int} */
    public function toArray(): array
    {
        return [
            'items' => array_map(
                static fn (Wishlist $wishlist): array => (new WishlistSummaryResponse($wishlist))->toArray(),
                $this->wishlists,
            ),
            'total' => count($this->wishlists),
        ];
    }
}
