<?php

declare(strict_types=1);

namespace App\Order\Application;

use App\Identity\Domain\Entity\User;
use App\Marketplace\Domain\Entity\Listing;
use App\Marketplace\Domain\Exception\ListingUnavailable;
use App\Marketplace\Domain\Repository\ListingRepository;
use App\Order\Application\Dto\AddCartItemRequest;
use App\Order\Application\Dto\CartResponse;
use App\Order\Domain\Entity\ShoppingCart;
use App\Order\Domain\Repository\ShoppingCartRepository;
use App\Order\Domain\Service\ShippingQuoteCalculator;
use App\Shared\Domain\Transaction\TransactionManager;

final readonly class AddCartItem
{
    public function __construct(
        private ShoppingCartRepository $carts,
        private ListingRepository $listings,
        private ShippingQuoteCalculator $shippingQuotes,
        private TransactionManager $transactions,
    ) {
    }

    public function add(User $buyer, AddCartItemRequest $request): CartResponse
    {
        $cart = $this->transactions->run(function () use ($buyer, $request): ShoppingCart {
            $cart = $this->carts->lockForCheckout($buyer) ?? new ShoppingCart($buyer);
            $listing = $this->listings->getActive($request->listingId);
            if (!$listing instanceof Listing) {
                throw new ListingUnavailable('Deze listing is niet meer beschikbaar.');
            }

            $cart->add($listing);
            $this->carts->save($cart);

            return $cart;
        });

        return new CartResponse($cart, $this->shippingQuotes);
    }
}
