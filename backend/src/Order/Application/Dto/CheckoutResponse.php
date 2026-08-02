<?php

declare(strict_types=1);

namespace App\Order\Application\Dto;

use App\Order\Domain\Entity\Order;
use App\Payment\Domain\Entity\Payment;

final readonly class CheckoutResponse
{
    /** @param list<Order> $orders */
    public function __construct(
        private Payment $payment,
        private array $orders,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'payment' => [
                'id' => $this->payment->getId()->toRfc4122(),
                'status' => $this->payment->getStatus()->value,
                'finalizationStatus' => $this->payment->getFinalizationStatus()->value,
                'idempotencyKey' => $this->payment->getIdempotencyKey(),
                'provider' => $this->payment->getProviderName(),
                'providerReference' => $this->payment->getProviderReference(),
                'amount' => $this->payment->getAmount()->toArray(),
            ],
            'orders' => array_map(
                fn (Order $order): array => (new OrderResponse($order, $this->payment->getBuyer()))->toArray(),
                $this->orders,
            ),
        ];
    }
}
