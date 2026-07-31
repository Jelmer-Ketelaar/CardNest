<?php

declare(strict_types=1);

namespace App\Wishlist\Application;

use App\Identity\Domain\Entity\User;
use App\Wishlist\Application\Dto\WishlistDetailResponse;
use App\Wishlist\Domain\Repository\WishlistRepository;

final readonly class DeleteWishlistItem
{
    public function __construct(
        private WishlistResolver $resolver,
        private WishlistRepository $wishlists,
        private FindWishlists $finder,
    ) {
    }

    public function delete(User $owner, string $wishlistId, string $itemId): WishlistDetailResponse
    {
        $item = $this->resolver->ownedItem($wishlistId, $itemId, $owner);
        $wishlist = $item->getWishlist();
        $this->wishlists->removeItem($item);

        return $this->finder->response($wishlist);
    }
}
