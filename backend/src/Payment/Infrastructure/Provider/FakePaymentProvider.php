<?php

declare(strict_types=1);

namespace App\Payment\Infrastructure\Provider;

use App\Payment\Domain\Provider\PaymentInstruction;
use App\Payment\Domain\Provider\PaymentProvider;
use App\Payment\Domain\Provider\PaymentResult;

final readonly class FakePaymentProvider implements PaymentProvider
{
    public function name(): string
    {
        return 'fake';
    }

    public function charge(PaymentInstruction $instruction): PaymentResult
    {
        return PaymentResult::succeeded(
            $instruction->amount,
            'fake_'.hash('sha256', $instruction->idempotencyKey),
        );
    }
}
