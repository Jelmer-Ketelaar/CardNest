<?php

declare(strict_types=1);

namespace App\Wishlist\Application;

use App\Identity\Domain\Entity\User;
use App\Wishlist\Application\Dto\CreateWishlistRequest;
use App\Wishlist\Application\Dto\WishlistDetailResponse;
use App\Wishlist\Domain\Entity\Wishlist;
use App\Wishlist\Domain\Repository\WishlistRepository;

final readonly class CreateWishlist
{
    public function __construct(private WishlistRepository $wishlists)
    {
    }

    public function create(User $owner, CreateWishlistRequest $request): WishlistDetailResponse
    {
        $wishlist = new Wishlist($owner, $request->name);
        $this->wishlists->save($wishlist);

        return new WishlistDetailResponse($wishlist);
    }
}
