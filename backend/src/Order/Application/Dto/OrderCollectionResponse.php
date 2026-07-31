<?php

declare(strict_types=1);

namespace App\Order\Application\Dto;

use App\Identity\Domain\Entity\User;
use App\Order\Domain\Entity\Order;

final readonly class OrderCollectionResponse
{
    /** @param list<Order> $orders */
    public function __construct(
        private array $orders,
        private User $participant,
    ) {
    }

    /** @return array{items: list<array<string, mixed>>, total: int} */
    public function toArray(): array
    {
        return [
            'items' => array_map(
                fn (Order $order): array => (new OrderResponse($order, $this->participant))->toArray(),
                $this->orders,
            ),
            'total' => count($this->orders),
        ];
    }
}
