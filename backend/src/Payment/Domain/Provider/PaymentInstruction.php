<?php

declare(strict_types=1);

namespace App\Payment\Domain\Provider;

use App\Shared\Domain\ValueObject\Money;

final readonly class PaymentInstruction
{
    public function __construct(
        public string $paymentId,
        public string $buyerId,
        public Money $amount,
    ) {
    }
}
