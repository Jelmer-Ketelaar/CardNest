<?php

declare(strict_types=1);

namespace App\Order\Infrastructure\Persistence;

use App\Identity\Domain\Entity\User;
use App\Order\Domain\Entity\Order;
use App\Order\Domain\Entity\OrderStatus;
use App\Order\Domain\Repository\OrderRepository;
use App\Payment\Domain\Entity\Payment;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;
use Throwable;

final readonly class DoctrineOrderRepository implements OrderRepository
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function add(Order $order): void
    {
        $this->entityManager->persist($order);
    }

    public function findForParticipant(User $participant): array
    {
        /** @var list<Order> $orders */
        $orders = $this->detailQuery()
            ->where('purchase.buyer = :participant OR purchase.seller = :participant')
            ->setParameter('participant', $participant)
            ->orderBy('purchase.createdAt', 'DESC')
            ->addOrderBy('purchase.id', 'DESC')
            ->getQuery()
            ->getResult();

        return $orders;
    }

    public function getForParticipant(string $id, User $participant): ?Order
    {
        try {
            $uuid = Uuid::fromString($id);
        } catch (Throwable) {
            return null;
        }

        /** @var Order|null $order */
        $order = $this->detailQuery()
            ->where('purchase.id = :id')
            ->andWhere('purchase.buyer = :participant OR purchase.seller = :participant')
            ->setParameter('id', $uuid, UuidType::NAME)
            ->setParameter('participant', $participant)
            ->getQuery()
            ->getOneOrNullResult();

        return $order;
    }

    public function lockForSeller(string $id, User $seller): ?Order
    {
        return $this->lockForParty($id, 'seller', $seller);
    }

    public function lockForBuyer(string $id, User $buyer): ?Order
    {
        return $this->lockForParty($id, 'buyer', $buyer);
    }

    public function lockForParticipant(string $id, User $participant): ?Order
    {
        try {
            $uuid = Uuid::fromString($id);
        } catch (Throwable) {
            return null;
        }

        /** @var Order|null $order */
        $order = $this->entityManager->createQueryBuilder()
            ->select('purchase')
            ->from(Order::class, 'purchase')
            ->where('purchase.id = :id')
            ->andWhere('purchase.buyer = :participant OR purchase.seller = :participant')
            ->setParameter('id', $uuid, UuidType::NAME)
            ->setParameter('participant', $participant)
            ->getQuery()
            ->setLockMode(LockMode::PESSIMISTIC_WRITE)
            ->getOneOrNullResult();

        return $order;
    }

    public function hasOtherUnrefundedPaidOrders(Payment $payment, Order $refundedOrder): bool
    {
        return (int) $this->entityManager->createQueryBuilder()
            ->select('COUNT(purchase.id)')
            ->from(Order::class, 'purchase')
            ->where('purchase.payment = :payment')
            ->andWhere('purchase.paidAt IS NOT NULL')
            ->andWhere('purchase.status != :refunded')
            ->andWhere('purchase != :refundedOrder')
            ->setParameter('payment', $payment)
            ->setParameter('refunded', OrderStatus::Refunded)
            ->setParameter('refundedOrder', $refundedOrder)
            ->getQuery()
            ->getSingleScalarResult() > 0;
    }

    private function detailQuery(): QueryBuilder
    {
        return $this->entityManager->createQueryBuilder()
            ->select(
                'purchase',
                'buyer',
                'seller',
                'payment',
                'transaction',
                'refund',
                'refundRequester',
                'address',
                'item',
                'listing',
                'ownedItem',
                'card',
                'cardSet',
                'variant',
                'importedItem',
                'importedCollection',
                'importedOwner',
                'history',
                'historyActor',
            )
            ->from(Order::class, 'purchase')
            ->join('purchase.buyer', 'buyer')
            ->join('purchase.seller', 'seller')
            ->join('purchase.payment', 'payment')
            ->leftJoin('payment.transactions', 'transaction')
            ->leftJoin('purchase.refund', 'refund')
            ->leftJoin('refund.requestedBy', 'refundRequester')
            ->join('purchase.shippingAddress', 'address')
            ->join('purchase.items', 'item')
            ->join('item.listing', 'listing')
            ->join('listing.collectionItem', 'ownedItem')
            ->join('ownedItem.catalogCard', 'card')
            ->join('card.set', 'cardSet')
            ->join('ownedItem.variant', 'variant')
            ->leftJoin('item.importedCollectionItem', 'importedItem')
            ->leftJoin('importedItem.collection', 'importedCollection')
            ->leftJoin('importedCollection.owner', 'importedOwner')
            ->leftJoin('purchase.statusHistory', 'history')
            ->leftJoin('history.actor', 'historyActor');
    }

    private function lockForParty(string $id, string $association, User $participant): ?Order
    {
        try {
            $uuid = Uuid::fromString($id);
        } catch (Throwable) {
            return null;
        }

        /** @var Order|null $order */
        $order = $this->entityManager->createQueryBuilder()
            ->select('purchase')
            ->from(Order::class, 'purchase')
            ->where('purchase.id = :id')
            ->andWhere('purchase.'.$association.' = :participant')
            ->setParameter('id', $uuid, UuidType::NAME)
            ->setParameter('participant', $participant)
            ->getQuery()
            ->setLockMode(LockMode::PESSIMISTIC_WRITE)
            ->getOneOrNullResult();

        return $order;
    }
}
