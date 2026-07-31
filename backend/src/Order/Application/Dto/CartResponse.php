<?php

declare(strict_types=1);

namespace App\Order\Application\Dto;

use App\Marketplace\Application\Dto\ListingResponse;
use App\Marketplace\Domain\Entity\Listing;
use App\Order\Domain\Entity\ShoppingCart;
use App\Order\Domain\Entity\ShoppingCartItem;
use App\Order\Domain\Service\ShippingQuoteCalculator;
use DateTimeImmutable;

final readonly class CartResponse
{
    public function __construct(
        private ?ShoppingCart $cart,
        private ShippingQuoteCalculator $shippingQuotes,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        if (!$this->cart instanceof ShoppingCart) {
            return [
                'id' => null,
                'sellerGroups' => [],
                'itemCount' => 0,
                'checkoutAvailable' => false,
            ];
        }

        $now = new DateTimeImmutable();
        /** @var array<string, array{sellerId: string, items: list<array<string, mixed>>, listings: list<Listing>}> $groups */
        $groups = [];
        $checkoutAvailable = true;
        foreach ($this->cart->getItems() as $item) {
            $listing = $item->getListing();
            $sellerId = $listing->getSeller()->getId()->toRfc4122();
            $available = $listing->isAvailableAt($now);
            $checkoutAvailable = $checkoutAvailable && $available;
            $groups[$sellerId] ??= ['sellerId' => $sellerId, 'items' => [], 'listings' => []];
            $groups[$sellerId]['items'][] = $this->item($item, $available);
            $groups[$sellerId]['listings'][] = $listing;
        }

        $sellerGroups = [];
        foreach ($groups as $group) {
            $subtotal = array_sum(array_map(
                static fn (Listing $listing): int => $listing->getPrice()->amount,
                $group['listings'],
            ));
            $quotes = $this->shippingQuotes->availableFor($group['listings']);
            if ($quotes === []) {
                $checkoutAvailable = false;
            }
            $sellerGroups[] = [
                'seller' => ['id' => $group['sellerId']],
                'items' => $group['items'],
                'itemSubtotal' => ['amount' => $subtotal, 'currency' => 'EUR'],
                'shippingMethods' => array_map(
                    static fn ($quote): array => [
                        'code' => $quote->code->value,
                        'price' => $quote->price->toArray(),
                        'totalPrice' => [
                            'amount' => $subtotal + $quote->price->amount,
                            'currency' => 'EUR',
                        ],
                    ],
                    $quotes,
                ),
            ];
        }

        return [
            'id' => $this->cart->getId()->toRfc4122(),
            'sellerGroups' => $sellerGroups,
            'itemCount' => $this->cart->getItems()->count(),
            'checkoutAvailable' => $checkoutAvailable && $this->cart->getItems()->count() > 0,
        ];
    }

    /** @return array<string, mixed> */
    private function item(ShoppingCartItem $item, bool $available): array
    {
        return [
            'id' => $item->getId()->toRfc4122(),
            'listing' => (new ListingResponse($item->getListing()))->toArray(),
            'addedPrice' => $item->getAddedPrice()->toArray(),
            'priceChanged' => $item->isPriceChanged(),
            'available' => $available,
            'addedAt' => $item->getCreatedAt()->format(DATE_ATOM),
        ];
    }
}
