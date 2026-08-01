<?php

declare(strict_types=1);

namespace App\Order\Application;

use App\Collection\Domain\Entity\CollectionItem;
use App\Collection\Domain\Entity\CollectionItemStatus;
use App\Collection\Domain\Repository\CollectionItemRepository;
use App\Identity\Domain\Entity\User;
use App\Marketplace\Domain\Entity\Listing;
use App\Marketplace\Domain\Repository\ListingRepository;
use App\Order\Application\Dto\CancelOrderRequest;
use App\Order\Application\Dto\OrderResponse;
use App\Order\Application\Exception\OrderNotFound;
use App\Order\Application\Exception\RefundFailed;
use App\Order\Domain\Entity\Order;
use App\Order\Domain\Entity\OrderItem;
use App\Order\Domain\Entity\OrderStatus;
use App\Order\Domain\Exception\InvalidOrder;
use App\Order\Domain\Exception\OrderNotCancellable;
use App\Order\Domain\Repository\OrderRepository;
use App\Order\Domain\ValueObject\CancellationReason;
use App\Payment\Domain\Entity\PaymentStatus;
use App\Payment\Domain\Entity\Refund;
use App\Payment\Domain\Provider\PaymentProvider;
use App\Payment\Domain\Provider\RefundInstruction;
use App\Payment\Domain\Repository\PaymentRepository;
use App\Payment\Domain\Repository\RefundRepository;
use App\Shared\Domain\Transaction\TransactionManager;
use DateTimeImmutable;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Workflow\WorkflowInterface;

final readonly class CancelOrder
{
    public function __construct(
        private OrderRepository $orders,
        private PaymentRepository $payments,
        private RefundRepository $refunds,
        private ListingRepository $listings,
        private CollectionItemRepository $collectionItems,
        private PaymentProvider $paymentProvider,
        private TransactionManager $transactions,
        private LoggerInterface $logger,
        #[Autowire(service: 'state_machine.order')]
        private WorkflowInterface $orderWorkflow,
        #[Autowire(service: 'state_machine.marketplace_listing')]
        private WorkflowInterface $listingWorkflow,
    ) {
    }

    public function cancel(User $actor, string $orderId, CancelOrderRequest $request): OrderResponse
    {
        $reason = new CancellationReason($request->reason);

        $order = $this->transactions->run(function () use ($actor, $orderId, $reason): Order {
            $this->logger->info('order_cancellation_requested', [
                'orderId' => $orderId,
                'actorId' => $actor->getId()->toRfc4122(),
            ]);

            $order = $this->orders->lockForParticipant($orderId, $actor);
            if (!$order instanceof Order) {
                $this->logger->warning('order_cancellation_rejected', [
                    'orderId' => $orderId,
                    'actorId' => $actor->getId()->toRfc4122(),
                    'reason' => 'not_found_or_not_participant',
                ]);
                throw new OrderNotFound('Order niet gevonden.');
            }
            if (!$this->orderWorkflow->can($order, 'cancel')) {
                $this->logger->warning('order_cancellation_rejected', [
                    'orderId' => $order->getId()->toRfc4122(),
                    'actorId' => $actor->getId()->toRfc4122(),
                    'status' => $order->getStatus()->value,
                    'reason' => 'invalid_status',
                ]);
                throw new OrderNotCancellable($this->notCancellableMessage($order));
            }

            $listings = $this->lockedListings($order);
            $items = array_map(
                static fn (Listing $listing): CollectionItem => $listing->getCollectionItem(),
                $listings,
            );
            $this->collectionItems->lockMany($items);
            foreach ($listings as $listing) {
                if (!$this->listingWorkflow->can($listing, 'cancel_order')) {
                    throw new OrderNotCancellable('De fysieke kaart kan niet meer veilig aan de order worden onttrokken.');
                }
            }

            $refund = null;
            if ($order->getStatus() === OrderStatus::Paid) {
                $refund = $this->refundPaidOrder($order, $actor, $reason);
            }

            $now = new DateTimeImmutable();
            $previousStatus = $order->getStatus();
            $this->orderWorkflow->apply($order, 'cancel');
            $order->recordStatusTransition($previousStatus, $actor, $now);
            $order->recordCancellation($actor, $reason, $now);

            foreach ($listings as $listing) {
                $this->listingWorkflow->apply($listing, 'cancel_order');
            }
            foreach ($items as $item) {
                $item->removeFromSale();
                if ($item->getStatus() !== CollectionItemStatus::InCollection) {
                    throw new InvalidOrder('Een geannuleerde order moet de fysieke kaart terugbrengen naar de collectie.');
                }
            }

            $this->logger->info('order_cancelled', [
                'orderId' => $order->getId()->toRfc4122(),
                'actorId' => $actor->getId()->toRfc4122(),
            ]);

            if ($refund instanceof Refund) {
                $this->completeRefund($order, $refund, $actor, $now);
            }

            return $order;
        });

        return new OrderResponse($order, $actor);
    }

    private function refundPaidOrder(Order $order, User $actor, CancellationReason $reason): Refund
    {
        $payment = $order->getPayment();
        $this->payments->lock($payment);
        if ($payment->getStatus() !== PaymentStatus::Succeeded || $payment->getProviderReference() === null) {
            throw new InvalidOrder('De order heeft geen succesvolle betaling die kan worden terugbetaald.');
        }

        $refund = new Refund(
            payment: $payment,
            order: $order,
            amount: $order->getTotal(),
            requestedBy: $actor,
            reason: $reason->value,
        );
        $this->refunds->add($refund);
        $result = $this->paymentProvider->refund(new RefundInstruction(
            paymentReference: $payment->getProviderReference(),
            amount: $refund->getAmount(),
            idempotencyKey: 'refund-order-'.$order->getId()->toRfc4122(),
            reason: $reason->value,
        ));
        if (!$result->succeeded || $result->providerReference === null) {
            $refund->markFailed('De terugbetaling kon niet door de betaalprovider worden verwerkt.');
            $this->logger->warning('order_refund_failed', [
                'orderId' => $order->getId()->toRfc4122(),
                'paymentId' => $payment->getId()->toRfc4122(),
                'refundId' => $refund->getId()->toRfc4122(),
                'actorId' => $actor->getId()->toRfc4122(),
                'amount' => $refund->getAmount()->amount,
                'currency' => $refund->getAmount()->currency,
                'failureType' => $result->failureReason === null ? 'missing_provider_reference' : 'provider_rejected',
            ]);
            throw new RefundFailed('De terugbetaling kon niet worden verwerkt. Probeer het opnieuw.');
        }

        $refund->markSucceeded($result->providerReference);

        return $refund;
    }

    private function completeRefund(Order $order, Refund $refund, User $actor, DateTimeImmutable $now): void
    {
        $payment = $order->getPayment();
        $payment->recordSuccessfulRefund($refund, $now);
        $previousStatus = $order->getStatus();
        $this->orderWorkflow->apply($order, 'mark_refunded');
        $order->recordStatusTransition($previousStatus, $actor, $now);

        $this->logger->info('order_refund_succeeded', [
            'orderId' => $order->getId()->toRfc4122(),
            'paymentId' => $payment->getId()->toRfc4122(),
            'refundId' => $refund->getId()->toRfc4122(),
            'actorId' => $actor->getId()->toRfc4122(),
            'amount' => $refund->getAmount()->amount,
            'currency' => $refund->getAmount()->currency,
        ]);

        if ($this->orders->hasOtherUnrefundedPaidOrders($payment, $order)) {
            return;
        }

        $payment->markFullyRefunded($now);
        $this->logger->info('payment_fully_refunded', [
            'paymentId' => $payment->getId()->toRfc4122(),
            'actorId' => $actor->getId()->toRfc4122(),
            'amount' => $payment->getAmount()->amount,
            'currency' => $payment->getAmount()->currency,
        ]);
    }

    /** @return list<Listing> */
    private function lockedListings(Order $order): array
    {
        $ids = array_values(array_map(
            static fn (OrderItem $item): string => $item->getListing()->getId()->toRfc4122(),
            $order->getItems()->toArray(),
        ));
        $listings = $this->listings->lockByIds($ids);
        if (count($listings) !== count($ids)) {
            throw new InvalidOrder('Niet alle fysieke kaarten van de order konden worden vergrendeld.');
        }

        return $listings;
    }

    private function notCancellableMessage(Order $order): string
    {
        return $order->getStatus() === OrderStatus::Shipped
            ? 'Een verzonden order kan niet worden geannuleerd.'
            : 'Een order kan alleen worden geannuleerd voordat deze is verzonden.';
    }
}
