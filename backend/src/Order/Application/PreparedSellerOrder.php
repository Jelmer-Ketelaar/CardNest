<?php

declare(strict_types=1);

namespace App\Order\Application;

use App\Order\Domain\ValueObject\ShippingQuote;

final readonly class PreparedSellerOrder
{
    public function __construct(
        public CheckoutSellerGroup $group,
        public ShippingQuote $shippingQuote,
    ) {
    }

    public function totalAmount(): int
    {
        return $this->group->itemSubtotal() + $this->shippingQuote->price->amount;
    }
}
