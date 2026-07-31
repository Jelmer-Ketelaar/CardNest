<?php

declare(strict_types=1);

namespace App\Marketplace\Application;

use App\Collection\Domain\Entity\CardCondition;
use App\Collection\Domain\Entity\CardLanguage;
use App\Identity\Domain\Entity\User;
use App\Marketplace\Application\Dto\ListingCollectionResponse;
use App\Marketplace\Application\Dto\ListingResponse;
use App\Marketplace\Application\Dto\ListListingsRequest;
use App\Marketplace\Domain\Entity\ShippingMethodCode;
use App\Marketplace\Domain\Repository\ListingRepository;
use App\Marketplace\Domain\Repository\ListingSearchCriteria;

final readonly class FindListings
{
    public function __construct(
        private ListingResolver $resolver,
        private ListingRepository $listings,
    ) {
    }

    public function search(ListListingsRequest $request): ListingCollectionResponse
    {
        return ListingCollectionResponse::fromSearchResult($this->listings->searchActive(new ListingSearchCriteria(
            cardId: $request->cardId,
            setId: $request->setId,
            cardName: $request->cardName,
            cardNumber: $request->cardNumber,
            language: $request->language !== null ? CardLanguage::from($request->language) : null,
            variant: $request->variant,
            condition: $request->condition !== null ? CardCondition::from($request->condition) : null,
            minimumPrice: $request->minimumPrice,
            maximumPrice: $request->maximumPrice,
            shippingMethod: $request->shippingMethod !== null
                ? ShippingMethodCode::from($request->shippingMethod)
                : null,
            pickupAllowed: $request->pickupAllowed,
            page: $request->page,
            perPage: $request->perPage,
        )));
    }

    public function get(string $id): ListingResponse
    {
        return new ListingResponse($this->resolver->active($id));
    }

    public function listForSeller(User $seller): ListingCollectionResponse
    {
        return ListingCollectionResponse::forSeller($this->listings->findAllForSeller($seller));
    }

    public function getForSeller(User $seller, string $id): ListingResponse
    {
        return new ListingResponse($this->resolver->owned($id, $seller), true);
    }
}
