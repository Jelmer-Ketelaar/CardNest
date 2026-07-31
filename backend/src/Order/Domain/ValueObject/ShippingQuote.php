<?php

declare(strict_types=1);

namespace App\Order\Domain\ValueObject;

use App\Marketplace\Domain\Entity\ShippingMethodCode;
use App\Shared\Domain\ValueObject\Money;

final readonly class ShippingQuote
{
    public function __construct(
        public ShippingMethodCode $code,
        public Money $price,
    ) {
    }
}
