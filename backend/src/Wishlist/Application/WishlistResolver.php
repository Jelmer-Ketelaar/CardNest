<?php

declare(strict_types=1);

namespace App\Wishlist\Application;

use App\Identity\Domain\Entity\User;
use App\Wishlist\Application\Exception\WishlistItemNotFound;
use App\Wishlist\Application\Exception\WishlistNotFound;
use App\Wishlist\Domain\Entity\Wishlist;
use App\Wishlist\Domain\Entity\WishlistItem;
use App\Wishlist\Domain\Repository\WishlistRepository;

final readonly class WishlistResolver
{
    public function __construct(private WishlistRepository $wishlists)
    {
    }

    public function owned(string $id, User $owner): Wishlist
    {
        $wishlist = $this->wishlists->getForOwner($id, $owner);
        if (!$wishlist instanceof Wishlist) {
            throw new WishlistNotFound('Wishlist niet gevonden.');
        }

        return $wishlist;
    }

    public function ownedItem(string $wishlistId, string $itemId, User $owner): WishlistItem
    {
        $item = $this->wishlists->getItemForOwner($wishlistId, $itemId, $owner);
        if (!$item instanceof WishlistItem) {
            throw new WishlistItemNotFound('Wishlist-item niet gevonden.');
        }

        return $item;
    }
}
