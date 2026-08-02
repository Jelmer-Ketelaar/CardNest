<?php

declare(strict_types=1);

namespace App\Order\Application;

use App\Payment\Domain\Entity\Payment;

interface CheckoutFaultInjector
{
    public function trigger(CheckoutCheckpoint $checkpoint, Payment $payment): void;
}
