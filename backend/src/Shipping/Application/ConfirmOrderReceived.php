<?php

declare(strict_types=1);

namespace App\Shipping\Application;

use App\Identity\Domain\Entity\User;
use App\Order\Application\Dto\OrderResponse;
use App\Order\Application\Exception\OrderNotFound;
use App\Order\Domain\Entity\Order;
use App\Order\Domain\Exception\InvalidOrderTransition;
use App\Order\Domain\Repository\OrderRepository;
use App\Shared\Domain\Transaction\TransactionManager;
use App\Shipping\Domain\Entity\Shipment;
use App\Shipping\Domain\Repository\ShipmentRepository;
use App\Storage\Domain\Repository\CollectionItemLocationRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Workflow\WorkflowInterface;

final readonly class ConfirmOrderReceived
{
    public function __construct(
        private OrderRepository $orders,
        private ShipmentRepository $shipments,
        private CollectionItemLocationRepository $locations,
        private TransactionManager $transactions,
        #[Autowire(service: 'state_machine.order')]
        private WorkflowInterface $orderWorkflow,
    ) {
    }

    public function confirm(User $buyer, string $orderId): OrderResponse
    {
        $order = $this->transactions->run(function () use ($buyer, $orderId): Order {
            $order = $this->orders->lockForBuyer($orderId, $buyer);
            if (!$order instanceof Order) {
                throw new OrderNotFound('Order niet gevonden.');
            }
            if (!$this->orderWorkflow->can($order, 'confirm_received')) {
                throw new InvalidOrderTransition('Alleen een verzonden order kan als ontvangen worden bevestigd.');
            }

            $shipment = $this->shipments->getForOrder($order);
            if (!$shipment instanceof Shipment) {
                throw new InvalidOrderTransition('De order heeft geen actieve shipment.');
            }

            $previousStatus = $order->getStatus();
            $this->orderWorkflow->apply($order, 'confirm_received');
            $order->recordStatusTransition($previousStatus, $buyer);
            $shipment->confirmDelivered();

            $items = $order->getItems()->toArray();
            usort(
                $items,
                static fn ($left, $right): int => $left->getListing()->getCollectionItem()->getId()->toRfc4122()
                    <=> $right->getListing()->getCollectionItem()->getId()->toRfc4122(),
            );
            foreach ($items as $orderItem) {
                $collectionItem = $orderItem->getListing()->getCollectionItem();
                $this->locations->removeCurrent($collectionItem);
                $collectionItem->completeSale();
            }

            return $order;
        });

        return new OrderResponse($order, $buyer);
    }
}
