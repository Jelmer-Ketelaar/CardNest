<?php

declare(strict_types=1);

namespace App\Payment\Domain\Provider;

use App\Shared\Domain\ValueObject\Money;

final readonly class RefundInstruction
{
    public function __construct(
        public string $paymentReference,
        public Money $amount,
        public string $idempotencyKey,
        public string $reason,
    ) {
    }
}
