<?php

declare(strict_types=1);

namespace App\Payment\Domain\Provider;

final readonly class PaymentResult
{
    private function __construct(
        public bool $succeeded,
        public ?string $providerReference,
        public ?string $failureReason,
    ) {
    }

    public static function succeeded(string $providerReference): self
    {
        return new self(true, $providerReference, null);
    }

    public static function failed(string $failureReason): self
    {
        return new self(false, null, $failureReason);
    }
}
