<?php

declare(strict_types=1);

namespace App\Marketplace\Application\Dto;

use App\Marketplace\Domain\Entity\Listing;
use App\Marketplace\Domain\Entity\ListingImage;
use App\Marketplace\Domain\Entity\ListingPriceHistory;
use App\Marketplace\Domain\Entity\ListingShippingMethod;

final readonly class ListingResponse
{
    public function __construct(
        private Listing $listing,
        private bool $includePrivateFields = false,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $item = $this->listing->getCollectionItem();
        $card = $item->getCatalogCard();
        $price = $this->listing->getPrice();
        $response = [
            'id' => $this->listing->getId()->toRfc4122(),
            'status' => $this->listing->getStatus()->value,
            'seller' => ['id' => $this->listing->getSeller()->getId()->toRfc4122()],
            'card' => [
                'id' => $card->getId()->toRfc4122(),
                'name' => $card->getName(),
                'number' => $card->getNumber(),
                'set' => [
                    'id' => $card->getSet()->getId()->toRfc4122(),
                    'name' => $card->getSet()->getName(),
                ],
            ],
            'language' => $item->getLanguage()->value,
            'variant' => $item->getVariant()->getCode()->value,
            'condition' => $item->getCondition()->value,
            'price' => $price->toArray(),
            'description' => $this->listing->getDescription(),
            'images' => array_map(
                static fn (ListingImage $image): array => [
                    'id' => $image->getId()->toRfc4122(),
                    'side' => $image->getSide()->value,
                    'url' => $image->getUrl(),
                ],
                $this->listing->getImages()->toArray(),
            ),
            'shippingMethods' => array_map(
                static fn (ListingShippingMethod $shipping): array => [
                    'code' => $shipping->getCode()->value,
                    'price' => $shipping->getPrice()->toArray(),
                    'totalPrice' => [
                        'amount' => $price->amount + $shipping->getPrice()->amount,
                        'currency' => $price->currency,
                    ],
                ],
                $this->listing->getShippingMethods()->toArray(),
            ),
            'priceHistory' => array_map(
                static fn (ListingPriceHistory $history): array => [
                    'previousPrice' => $history->getPreviousPrice()?->toArray(),
                    'newPrice' => $history->getNewPrice()->toArray(),
                    'changedAt' => $history->getChangedAt()->format(DATE_ATOM),
                ],
                $this->listing->getPriceHistory()->toArray(),
            ),
            'expiresAt' => $this->listing->getExpiresAt()?->format(DATE_ATOM),
            'publishedAt' => $this->listing->getPublishedAt()?->format(DATE_ATOM),
            'createdAt' => $this->listing->getCreatedAt()->format(DATE_ATOM),
            'updatedAt' => $this->listing->getUpdatedAt()->format(DATE_ATOM),
        ];
        if ($this->includePrivateFields) {
            $response['collectionItemId'] = $item->getId()->toRfc4122();
        }

        return $response;
    }
}
