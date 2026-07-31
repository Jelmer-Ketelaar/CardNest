<?php

declare(strict_types=1);

namespace App\Wishlist\Application;

use App\Collection\Domain\Entity\CardCondition;
use App\Collection\Domain\Entity\CardLanguage;
use App\Collection\Domain\Repository\CollectionItemRepository;
use App\Identity\Domain\Entity\User;
use App\Shared\Domain\ValueObject\Money;
use App\Wishlist\Application\Dto\AddWishlistItemRequest;
use App\Wishlist\Application\Dto\WishlistDetailResponse;
use App\Wishlist\Domain\Entity\WishlistItem;
use App\Wishlist\Domain\Entity\WishlistPriority;
use App\Wishlist\Domain\Repository\WishlistRepository;

final readonly class AddWishlistItem
{
    public function __construct(
        private WishlistResolver $resolver,
        private WishlistReferenceResolver $references,
        private CollectionItemRepository $collectionItems,
        private WishlistRepository $wishlists,
    ) {
    }

    public function add(User $owner, string $wishlistId, AddWishlistItemRequest $request): WishlistDetailResponse
    {
        $wishlist = $this->resolver->owned($wishlistId, $owner);
        $card = $this->references->card($request->catalogCardId);
        $variant = $this->references->optionalVariant($card, $request->variantId);
        $language = $request->language !== null ? CardLanguage::from($request->language) : null;
        $condition = $request->minimumCondition !== null
            ? CardCondition::from($request->minimumCondition)
            : null;
        $maxPrice = $request->maxPrice !== null && $request->maxCurrency !== null
            ? new Money($request->maxPrice, $request->maxCurrency)
            : null;

        $item = new WishlistItem(
            wishlist: $wishlist,
            catalogCard: $card,
            desiredLanguage: $language,
            desiredVariant: $variant,
            minimumCondition: $condition,
            maxPrice: $maxPrice,
            desiredQuantity: $request->desiredQuantity,
            priority: WishlistPriority::from($request->priority),
            notes: $request->notes,
        );
        $wishlist->addItem($item);

        $alreadyOwned = $this->collectionItems->countMatching(
            $owner,
            $card,
            $language,
            $variant,
            $condition,
            $maxPrice,
        );
        if ($alreadyOwned > 0) {
            $item->fulfill($alreadyOwned);
        }

        $this->wishlists->save($wishlist);

        return new WishlistDetailResponse($wishlist);
    }
}
