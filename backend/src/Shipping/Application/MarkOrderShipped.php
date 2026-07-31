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
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Workflow\WorkflowInterface;

final readonly class MarkOrderShipped
{
    public function __construct(
        private OrderRepository $orders,
        private ShipmentRepository $shipments,
        private TransactionManager $transactions,
        #[Autowire(service: 'state_machine.order')]
        private WorkflowInterface $orderWorkflow,
    ) {
    }

    public function mark(User $seller, string $orderId): OrderResponse
    {
        $order = $this->transactions->run(function () use ($seller, $orderId): Order {
            $order = $this->orders->lockForSeller($orderId, $seller);
            if (!$order instanceof Order) {
                throw new OrderNotFound('Order niet gevonden.');
            }
            if (!$this->orderWorkflow->can($order, 'mark_shipped')) {
                throw new InvalidOrderTransition('Alleen een betaalde order kan als verzonden worden gemarkeerd.');
            }

            $previousStatus = $order->getStatus();
            $this->orderWorkflow->apply($order, 'mark_shipped');
            $order->recordStatusTransition($previousStatus, $seller);
            $this->shipments->add(Shipment::forOrder($order));

            return $order;
        });

        return new OrderResponse($order, $seller);
    }
}
