<?php

declare(strict_types=1);

namespace App\Wishlist\Application\Dto;

use App\Wishlist\Domain\Entity\Wishlist;
use App\Wishlist\Domain\Entity\WishlistItem;

final readonly class WishlistDetailResponse
{
    public function __construct(private Wishlist $wishlist)
    {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            ...(new WishlistSummaryResponse($this->wishlist))->toArray(),
            'items' => array_map(
                static fn (WishlistItem $item): array => (new WishlistItemResponse($item))->toArray(),
                $this->wishlist->getItems()->toArray(),
            ),
        ];
    }
}
