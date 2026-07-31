<?php

declare(strict_types=1);

namespace App\Order\Infrastructure\Persistence;

use App\Identity\Domain\Entity\User;
use App\Order\Domain\Entity\ShoppingCart;
use App\Order\Domain\Repository\ShoppingCartRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;

final readonly class DoctrineShoppingCartRepository implements ShoppingCartRepository
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function getForBuyer(User $buyer): ?ShoppingCart
    {
        /** @var ShoppingCart|null $cart */
        $cart = $this->detailQuery()
            ->where('cart.buyer = :buyer')
            ->setParameter('buyer', $buyer)
            ->getQuery()
            ->getOneOrNullResult();

        return $cart;
    }

    public function lockForCheckout(User $buyer): ?ShoppingCart
    {
        // The user row is always present and serializes cart creation as well
        // as mutations when a cart does not exist yet.
        $this->entityManager->lock($buyer, LockMode::PESSIMISTIC_WRITE);
        $cart = $this->getForBuyer($buyer);
        if (!$cart instanceof ShoppingCart) {
            return null;
        }

        // The buyer lock is acquired before loading the cart. Every cart
        // mutation follows this path, so the freshly queried cart is already
        // serialized without re-hydrating its immutable fields.
        $cart->getItems()->toArray();

        return $cart;
    }

    public function save(ShoppingCart $cart): void
    {
        $this->entityManager->persist($cart);
        $this->entityManager->flush();
    }

    private function detailQuery(): QueryBuilder
    {
        return $this->entityManager->createQueryBuilder()
            ->select('cart', 'item', 'listing', 'seller', 'ownedItem', 'card', 'cardSet', 'variant', 'image', 'shipping')
            ->from(ShoppingCart::class, 'cart')
            ->leftJoin('cart.items', 'item')
            ->leftJoin('item.listing', 'listing')
            ->leftJoin('listing.seller', 'seller')
            ->leftJoin('listing.collectionItem', 'ownedItem')
            ->leftJoin('ownedItem.catalogCard', 'card')
            ->leftJoin('card.set', 'cardSet')
            ->leftJoin('ownedItem.variant', 'variant')
            ->leftJoin('listing.images', 'image')
            ->leftJoin('listing.shippingMethods', 'shipping');
    }
}
