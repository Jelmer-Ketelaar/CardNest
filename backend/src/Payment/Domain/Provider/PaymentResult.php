<?php

declare(strict_types=1);

namespace App\Payment\Domain\Provider;

use App\Shared\Domain\ValueObject\Money;

final readonly class PaymentResult
{
    private function __construct(
        public bool $succeeded,
        public Money $amount,
        public ?string $providerReference,
        public ?string $failureReason,
    ) {
    }

    public static function succeeded(Money $amount, string $providerReference): self
    {
        return new self(true, $amount, $providerReference, null);
    }

    public static function failed(Money $amount, string $failureReason): self
    {
        return new self(false, $amount, null, $failureReason);
    }
}
