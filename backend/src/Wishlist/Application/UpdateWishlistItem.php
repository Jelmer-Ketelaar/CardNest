<?php

declare(strict_types=1);

namespace App\Wishlist\Application;

use App\Collection\Domain\Entity\CardCondition;
use App\Collection\Domain\Entity\CardLanguage;
use App\Collection\Domain\Repository\CollectionItemRepository;
use App\Identity\Domain\Entity\User;
use App\Shared\Domain\ValueObject\Money;
use App\Wishlist\Application\Dto\UpdateWishlistItemRequest;
use App\Wishlist\Application\Dto\WishlistDetailResponse;
use App\Wishlist\Domain\Entity\WishlistItem;
use App\Wishlist\Domain\Entity\WishlistPriority;
use App\Wishlist\Domain\Repository\WishlistRepository;

final readonly class UpdateWishlistItem
{
    public function __construct(
        private WishlistResolver $resolver,
        private WishlistReferenceResolver $references,
        private CollectionItemRepository $collectionItems,
        private WishlistRepository $wishlists,
        private FindWishlists $finder,
    ) {
    }

    public function update(
        User $owner,
        string $wishlistId,
        string $itemId,
        UpdateWishlistItemRequest $request,
    ): WishlistDetailResponse {
        $item = $this->resolver->ownedItem($wishlistId, $itemId, $owner);
        $language = $this->language($item, $request);
        $variant = $request->clearVariant
            ? null
            : $this->references->optionalVariant($item->getCatalogCard(), $request->variantId)
                ?? $item->getDesiredVariant();
        $condition = $this->condition($item, $request);
        $maxPrice = $this->maxPrice($item, $request);
        $notes = $request->clearNotes ? null : $request->notes ?? $item->getNotes();

        $item->updatePreferences(
            $language,
            $variant,
            $condition,
            $maxPrice,
            $request->desiredQuantity ?? $item->getDesiredQuantity(),
            $request->priority !== null ? WishlistPriority::from($request->priority) : $item->getPriority(),
            $notes,
        );
        $item->synchronizeFulfillment($this->collectionItems->countMatching(
            $owner,
            $item->getCatalogCard(),
            $language,
            $variant,
            $condition,
            $maxPrice,
        ));
        $this->wishlists->save($item->getWishlist());

        return $this->finder->response($item->getWishlist());
    }

    private function language(WishlistItem $item, UpdateWishlistItemRequest $request): ?CardLanguage
    {
        if ($request->clearLanguage) {
            return null;
        }

        return $request->language !== null ? CardLanguage::from($request->language) : $item->getDesiredLanguage();
    }

    private function condition(WishlistItem $item, UpdateWishlistItemRequest $request): ?CardCondition
    {
        if ($request->clearMinimumCondition) {
            return null;
        }

        return $request->minimumCondition !== null
            ? CardCondition::from($request->minimumCondition)
            : $item->getMinimumCondition();
    }

    private function maxPrice(WishlistItem $item, UpdateWishlistItemRequest $request): ?Money
    {
        if ($request->clearMaxPrice) {
            return null;
        }

        if ($request->maxPrice !== null && $request->maxCurrency !== null) {
            return new Money($request->maxPrice, $request->maxCurrency);
        }

        return $item->getMaxPrice();
    }
}
