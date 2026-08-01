<?php

declare(strict_types=1);

namespace App\Payment\Infrastructure\Provider;

use App\Payment\Domain\Provider\PaymentInstruction;
use App\Payment\Domain\Provider\PaymentProvider;
use App\Payment\Domain\Provider\PaymentResult;

final readonly class FailingFakePaymentProvider implements PaymentProvider
{
    public function name(): string
    {
        return 'fake';
    }

    public function charge(PaymentInstruction $instruction): PaymentResult
    {
        return PaymentResult::failed($instruction->amount, 'De testbetaling is afgewezen.');
    }
}
