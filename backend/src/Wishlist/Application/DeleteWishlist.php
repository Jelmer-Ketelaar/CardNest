<?php

declare(strict_types=1);

namespace App\Wishlist\Application;

use App\Identity\Domain\Entity\User;
use App\Wishlist\Domain\Repository\WishlistRepository;

final readonly class DeleteWishlist
{
    public function __construct(
        private WishlistResolver $resolver,
        private WishlistRepository $wishlists,
    ) {
    }

    public function delete(User $owner, string $id): void
    {
        $this->wishlists->remove($this->resolver->owned($id, $owner));
    }
}
