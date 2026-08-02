<?php

declare(strict_types=1);

namespace App\Payment\Domain\Repository;

use App\Marketplace\Domain\Entity\ListingReservation;
use App\Order\Domain\Entity\Order;
use App\Order\Domain\Entity\ShoppingCart;
use App\Payment\Domain\Entity\Payment;

interface PaymentRepository
{
    public function add(Payment $payment): void;

    public function findByIdempotencyKey(string $idempotencyKey): ?Payment;

    public function findLatestFinalizedCheckout(ShoppingCart $cart, string $requestFingerprint): ?Payment;

    public function lockById(string $id): ?Payment;

    public function lock(Payment $payment): void;

    /** @return list<Order> */
    public function findCheckoutOrders(Payment $payment): array;

    /** @return list<Order> */
    public function lockCheckoutOrders(Payment $payment): array;

    /** @return list<ListingReservation> */
    public function lockCheckoutReservations(Payment $payment): array;
}
