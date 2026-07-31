<?php

declare(strict_types=1);

namespace App\Wishlist\Application;

use App\Identity\Domain\Entity\User;
use App\Wishlist\Application\Dto\UpdateWishlistRequest;
use App\Wishlist\Application\Dto\WishlistDetailResponse;
use App\Wishlist\Domain\Repository\WishlistRepository;

final readonly class UpdateWishlist
{
    public function __construct(
        private WishlistResolver $resolver,
        private WishlistRepository $wishlists,
    ) {
    }

    public function update(User $owner, string $id, UpdateWishlistRequest $request): WishlistDetailResponse
    {
        $wishlist = $this->resolver->owned($id, $owner);
        $wishlist->rename($request->name);
        $this->wishlists->save($wishlist);

        return new WishlistDetailResponse($wishlist);
    }
}
