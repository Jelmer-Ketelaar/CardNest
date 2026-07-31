<?php

declare(strict_types=1);

namespace App\Wishlist\Domain\Repository;

use App\Catalog\Domain\Entity\CatalogCard;
use App\Identity\Domain\Entity\User;
use App\Wishlist\Domain\Entity\Wishlist;
use App\Wishlist\Domain\Entity\WishlistItem;

interface WishlistRepository
{
    /** @return list<Wishlist> */
    public function findAllForOwner(User $owner): array;

    public function getForOwner(string $id, User $owner): ?Wishlist;

    public function getItemForOwner(string $wishlistId, string $itemId, User $owner): ?WishlistItem;

    /** @return list<WishlistItem> */
    public function findOpenForOwnerAndCard(User $owner, CatalogCard $card): array;

    public function save(Wishlist $wishlist): void;

    public function remove(Wishlist $wishlist): void;

    public function removeItem(WishlistItem $item): void;
}
