<?php

declare(strict_types=1);

namespace App\Wishlist\Application;

use App\Identity\Domain\Entity\User;
use App\Wishlist\Application\Dto\WishlistCollectionResponse;
use App\Wishlist\Application\Dto\WishlistDetailResponse;
use App\Wishlist\Domain\Entity\Wishlist;
use App\Wishlist\Domain\Repository\WishlistRepository;

final readonly class FindWishlists
{
    public function __construct(
        private WishlistResolver $resolver,
        private WishlistRepository $wishlists,
    ) {
    }

    public function list(User $owner): WishlistCollectionResponse
    {
        return new WishlistCollectionResponse($this->wishlists->findAllForOwner($owner));
    }

    public function get(User $owner, string $id): WishlistDetailResponse
    {
        return $this->response($this->resolver->owned($id, $owner));
    }

    public function response(Wishlist $wishlist): WishlistDetailResponse
    {
        return new WishlistDetailResponse($wishlist);
    }
}
