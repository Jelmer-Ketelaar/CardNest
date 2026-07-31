<?php

declare(strict_types=1);

namespace App\Order\Domain\Service;

use App\Marketplace\Domain\Entity\Listing;
use App\Marketplace\Domain\Entity\ShippingMethodCode;
use App\Order\Domain\Exception\InvalidShippingSelection;
use App\Order\Domain\ValueObject\ShippingQuote;
use App\Shared\Domain\ValueObject\Money;

final readonly class ShippingQuoteCalculator
{
    /**
     * The cards of one seller ship together. A method is available only when
     * every listing supports it; the highest individual shipping price covers
     * the combined shipment without summing postage per card.
     *
     * @param list<Listing> $listings
     *
     * @return list<ShippingQuote>
     */
    public function availableFor(array $listings): array
    {
        if ($listings === []) {
            return [];
        }

        $quotes = [];
        foreach (ShippingMethodCode::cases() as $code) {
            $maximum = 0;
            foreach ($listings as $listing) {
                $price = $listing->shippingPriceFor($code);
                if (!$price instanceof Money) {
                    continue 2;
                }
                $maximum = max($maximum, $price->amount);
            }
            $quotes[] = new ShippingQuote($code, new Money($maximum, 'EUR'));
        }

        return $quotes;
    }

    /** @param list<Listing> $listings */
    public function require(array $listings, ShippingMethodCode $code): ShippingQuote
    {
        foreach ($this->availableFor($listings) as $quote) {
            if ($quote->code === $code) {
                return $quote;
            }
        }

        throw new InvalidShippingSelection('De gekozen verzendmethode is niet voor alle kaarten beschikbaar.');
    }
}
