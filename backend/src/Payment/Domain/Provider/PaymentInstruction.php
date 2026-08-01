<?php

declare(strict_types=1);

namespace App\Payment\Domain\Provider;

use App\Shared\Domain\ValueObject\Money;
use InvalidArgumentException;

final readonly class PaymentInstruction
{
    public function __construct(
        public string $paymentId,
        public string $buyerId,
        public Money $amount,
        public string $idempotencyKey,
    ) {
        if (trim($idempotencyKey) === '' || mb_strlen($idempotencyKey) > 255) {
            throw new InvalidArgumentException('A payment instruction requires an idempotency key of at most 255 characters.');
        }
    }
}
