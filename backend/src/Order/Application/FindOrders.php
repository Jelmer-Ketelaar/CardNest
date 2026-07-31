<?php

declare(strict_types=1);

namespace App\Order\Application;

use App\Identity\Domain\Entity\User;
use App\Order\Application\Dto\OrderCollectionResponse;
use App\Order\Application\Dto\OrderResponse;
use App\Order\Application\Exception\OrderNotFound;
use App\Order\Domain\Entity\Order;
use App\Order\Domain\Repository\OrderRepository;

final readonly class FindOrders
{
    public function __construct(private OrderRepository $orders)
    {
    }

    public function list(User $participant): OrderCollectionResponse
    {
        return new OrderCollectionResponse($this->orders->findForParticipant($participant), $participant);
    }

    public function get(User $participant, string $id): OrderResponse
    {
        $order = $this->orders->getForParticipant($id, $participant);
        if (!$order instanceof Order) {
            throw new OrderNotFound('Order niet gevonden.');
        }

        return new OrderResponse($order, $participant);
    }
}
