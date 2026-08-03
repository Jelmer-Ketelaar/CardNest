<?php

declare(strict_types=1);

namespace App\Marketplace\Application;

use App\Collection\Application\FindCollectionItems;
use App\Identity\Domain\Entity\User;
use App\Marketplace\Application\Dto\CreateListingRequest;
use App\Marketplace\Application\Dto\ListingResponse;
use App\Marketplace\Domain\Entity\Listing;
use App\Marketplace\Domain\Entity\ListingImageSide;
use App\Marketplace\Domain\Entity\ShippingMethodCode;
use App\Marketplace\Domain\Exception\InvalidListing;
use App\Marketplace\Domain\Repository\ListingRepository;
use App\Marketplace\Domain\ValueObject\ListingPhotoReference;
use App\Marketplace\Domain\ValueObject\ShippingOption;
use App\Shared\Domain\ValueObject\Money;
use DateTimeImmutable;

final readonly class CreateListing
{
    public function __construct(
        private FindCollectionItems $collectionItems,
        private ListingRepository $listings,
    ) {
    }

    public function create(User $seller, CreateListingRequest $request): ListingResponse
    {
        $collectionItem = $this->collectionItems->findOwnedItem($seller, $request->collectionItemId);
        $catalogImageUrl = $collectionItem->getCatalogCard()->getImageUrl()
            ?? $collectionItem->getCatalogCard()->getThumbnailUrl();
        if ($catalogImageUrl === null) {
            throw new InvalidListing('Deze cataloguskaart heeft nog geen officiële afbeelding.');
        }

        $listing = new Listing(
            seller: $seller,
            collectionItem: $collectionItem,
            price: new Money($request->price, $request->currency),
            shippingOptions: $this->shippingOptions($request),
            photos: [
                new ListingPhotoReference(ListingImageSide::Front, $catalogImageUrl),
                new ListingPhotoReference(ListingImageSide::Back, $catalogImageUrl),
            ],
            description: $request->description,
            expiresAt: $request->expiresAt !== null ? new DateTimeImmutable($request->expiresAt) : null,
        );
        $this->listings->save($listing);

        return new ListingResponse($listing, true);
    }

    /** @return list<ShippingOption> */
    private function shippingOptions(CreateListingRequest $request): array
    {
        $options = [];
        if ($request->letterMailPrice !== null) {
            $options[] = new ShippingOption(
                ShippingMethodCode::LetterMail,
                new Money($request->letterMailPrice, 'EUR'),
            );
        }
        if ($request->parcelPrice !== null) {
            $options[] = new ShippingOption(
                ShippingMethodCode::Parcel,
                new Money($request->parcelPrice, 'EUR'),
            );
        }
        if ($request->pickupAllowed) {
            $options[] = new ShippingOption(ShippingMethodCode::Pickup, new Money(0, 'EUR'));
        }

        return $options;
    }
}
