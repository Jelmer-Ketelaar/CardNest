<?php

declare(strict_types=1);

namespace App\Payment\Infrastructure\Persistence;

use App\Marketplace\Domain\Entity\ListingReservation;
use App\Order\Domain\Entity\Order;
use App\Order\Domain\Entity\ShoppingCart;
use App\Payment\Domain\Entity\Payment;
use App\Payment\Domain\Entity\PaymentFinalizationStatus;
use App\Payment\Domain\Repository\PaymentRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Symfony\Component\Uid\Uuid;
use Throwable;

final readonly class DoctrinePaymentRepository implements PaymentRepository
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function add(Payment $payment): void
    {
        $this->entityManager->persist($payment);
    }

    public function findByIdempotencyKey(string $idempotencyKey): ?Payment
    {
        $query = $this->entityManager->createQueryBuilder()
            ->select('payment')
            ->from(Payment::class, 'payment')
            ->where('payment.idempotencyKey = :idempotencyKey')
            ->setParameter('idempotencyKey', $idempotencyKey)
            ->getQuery();
        $query->setHint(Query::HINT_REFRESH, true);

        /** @var Payment|null $payment */
        $payment = $query->getOneOrNullResult();

        return $payment;
    }

    public function findLatestFinalizedCheckout(ShoppingCart $cart, string $requestFingerprint): ?Payment
    {
        $query = $this->entityManager->createQueryBuilder()
            ->select('payment')
            ->from(Payment::class, 'payment')
            ->where('payment.checkoutCart = :cart')
            ->andWhere('payment.requestFingerprint = :requestFingerprint')
            ->andWhere('payment.finalizationStatus = :finalized')
            ->setParameter('cart', $cart)
            ->setParameter('requestFingerprint', $requestFingerprint)
            ->setParameter('finalized', PaymentFinalizationStatus::Finalized)
            ->orderBy('payment.finalizedAt', 'DESC')
            ->addOrderBy('payment.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery();
        $query->setHint(Query::HINT_REFRESH, true);

        /** @var Payment|null $payment */
        $payment = $query->getOneOrNullResult();

        return $payment;
    }

    public function reloadAndLockById(string $id): ?Payment
    {
        try {
            $uuid = Uuid::fromString($id);
        } catch (Throwable) {
            return null;
        }

        // Checkout spans multiple database transactions around the provider call.
        // Start every locked phase with a fresh identity map so concurrent updates
        // are hydrated without rewriting readonly value-object properties.
        $this->entityManager->clear();
        $lockedId = $this->entityManager->getConnection()->fetchOne(
            'SELECT id FROM payments WHERE id = CAST(:id AS uuid) FOR UPDATE',
            ['id' => $uuid->toRfc4122()],
        );
        if ($lockedId === false) {
            return null;
        }

        $payment = $this->entityManager->find(Payment::class, $uuid);

        return $payment instanceof Payment ? $payment : null;
    }

    public function lock(Payment $payment): void
    {
        $this->entityManager->lock($payment, LockMode::PESSIMISTIC_WRITE);
    }

    public function findCheckoutOrders(Payment $payment): array
    {
        /** @var list<Order> $orders */
        $orders = $this->entityManager->createQueryBuilder()
            ->select(
                'purchase',
                'buyer',
                'seller',
                'address',
                'item',
                'listing',
                'ownedItem',
                'card',
                'cardSet',
                'variant',
                'history',
                'historyActor',
            )
            ->from(Order::class, 'purchase')
            ->join('purchase.buyer', 'buyer')
            ->join('purchase.seller', 'seller')
            ->join('purchase.shippingAddress', 'address')
            ->join('purchase.items', 'item')
            ->join('item.listing', 'listing')
            ->join('listing.collectionItem', 'ownedItem')
            ->join('ownedItem.catalogCard', 'card')
            ->join('card.set', 'cardSet')
            ->join('ownedItem.variant', 'variant')
            ->leftJoin('purchase.statusHistory', 'history')
            ->leftJoin('history.actor', 'historyActor')
            ->where('purchase.payment = :payment')
            ->setParameter('payment', $payment)
            ->orderBy('purchase.id', 'ASC')
            ->getQuery()
            ->getResult();

        return $orders;
    }

    public function lockCheckoutOrders(Payment $payment): array
    {
        $ids = $this->entityManager->createQueryBuilder()
            ->select('purchase.id')
            ->from(Order::class, 'purchase')
            ->where('purchase.payment = :payment')
            ->setParameter('payment', $payment)
            ->orderBy('purchase.id', 'ASC')
            ->getQuery()
            ->getSingleColumnResult();

        $orders = [];
        foreach ($ids as $id) {
            $lockedId = $this->entityManager->getConnection()->fetchOne(
                'SELECT id FROM orders WHERE id = CAST(:id AS uuid) FOR UPDATE',
                ['id' => (string) $id],
            );
            if ($lockedId === false) {
                continue;
            }

            $order = $this->entityManager->find(Order::class, $id);
            if (!$order instanceof Order) {
                continue;
            }
            $order->getItems()->toArray();
            $order->getStatusHistory()->toArray();
            $orders[] = $order;
        }

        return $orders;
    }

    public function lockCheckoutReservations(Payment $payment): array
    {
        $ids = $this->entityManager->createQueryBuilder()
            ->select('reservation.id')
            ->from(ListingReservation::class, 'reservation')
            ->where('reservation.checkoutReference = :checkoutReference')
            ->setParameter('checkoutReference', $payment->getId())
            ->orderBy('reservation.id', 'ASC')
            ->getQuery()
            ->getSingleColumnResult();

        $reservations = [];
        foreach ($ids as $id) {
            $lockedId = $this->entityManager->getConnection()->fetchOne(
                'SELECT id FROM listing_reservations WHERE id = CAST(:id AS uuid) FOR UPDATE',
                ['id' => (string) $id],
            );
            if ($lockedId === false) {
                continue;
            }

            $reservation = $this->entityManager->find(ListingReservation::class, $id);
            if (!$reservation instanceof ListingReservation) {
                continue;
            }
            $reservations[] = $reservation;
        }

        return $reservations;
    }
}
