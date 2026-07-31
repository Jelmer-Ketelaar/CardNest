<?php

declare(strict_types=1);

namespace App\Order\Application;

use App\Identity\Domain\Entity\User;
use App\Order\Application\Dto\CartResponse;
use App\Order\Domain\Repository\ShoppingCartRepository;
use App\Order\Domain\Service\ShippingQuoteCalculator;

final readonly class FindCart
{
    public function __construct(
        private ShoppingCartRepository $carts,
        private ShippingQuoteCalculator $shippingQuotes,
    ) {
    }

    public function get(User $buyer): CartResponse
    {
        return new CartResponse($this->carts->getForBuyer($buyer), $this->shippingQuotes);
    }
}
