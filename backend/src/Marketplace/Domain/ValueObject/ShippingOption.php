<?php

declare(strict_types=1);

namespace App\Marketplace\Domain\ValueObject;

use App\Marketplace\Domain\Entity\ShippingMethodCode;
use App\Marketplace\Domain\Exception\InvalidListing;
use App\Shared\Domain\ValueObject\Money;

final readonly class ShippingOption
{
    public function __construct(
        public ShippingMethodCode $code,
        public Money $price,
    ) {
        if ($price->currency !== 'EUR') {
            throw new InvalidListing('Verzendopties ondersteunen momenteel alleen EUR.');
        }
        if ($code === ShippingMethodCode::Pickup && $price->amount !== 0) {
            throw new InvalidListing('Ophalen kan geen verzendkosten hebben.');
        }
    }
}
