<?php

declare(strict_types=1);

namespace App\Order\Application\Dto;

use App\Order\Domain\Entity\Order;

final readonly class OrderCollectionResponse
{
    /** @param list<Order> $orders */
    public function __construct(private array $orders)
    {
    }

    /** @return array{items: list<array<string, mixed>>, total: int} */
    public function toArray(): array
    {
        return [
            'items' => array_map(
                static fn (Order $order): array => (new OrderResponse($order))->toArray(),
                $this->orders,
            ),
            'total' => count($this->orders),
        ];
    }
}
