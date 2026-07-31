<?php

declare(strict_types=1);

namespace App\Order\Application;

use App\Identity\Domain\Entity\User;
use App\Order\Application\Dto\CartResponse;
use App\Order\Domain\Entity\ShoppingCart;
use App\Order\Domain\Exception\CartItemNotFound;
use App\Order\Domain\Repository\ShoppingCartRepository;
use App\Order\Domain\Service\ShippingQuoteCalculator;
use App\Shared\Domain\Transaction\TransactionManager;

final readonly class RemoveCartItem
{
    public function __construct(
        private ShoppingCartRepository $carts,
        private ShippingQuoteCalculator $shippingQuotes,
        private TransactionManager $transactions,
    ) {
    }

    public function remove(User $buyer, string $itemId): CartResponse
    {
        $cart = $this->transactions->run(function () use ($buyer, $itemId): ShoppingCart {
            $cart = $this->carts->lockForCheckout($buyer);
            if (!$cart instanceof ShoppingCart) {
                throw new CartItemNotFound('Winkelmanditem niet gevonden.');
            }

            $cart->remove($itemId);
            $this->carts->save($cart);

            return $cart;
        });

        return new CartResponse($cart, $this->shippingQuotes);
    }
}
