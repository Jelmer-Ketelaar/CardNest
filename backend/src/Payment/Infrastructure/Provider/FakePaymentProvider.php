<?php

declare(strict_types=1);

namespace App\Payment\Infrastructure\Provider;

use App\Payment\Domain\Provider\PaymentInstruction;
use App\Payment\Domain\Provider\PaymentProvider;
use App\Payment\Domain\Provider\PaymentResult;
use App\Payment\Domain\Provider\RefundInstruction;
use App\Payment\Domain\Provider\RefundResult;

final readonly class FakePaymentProvider implements PaymentProvider
{
    public function __construct(private bool $refundsShouldFail = false)
    {
    }

    public function name(): string
    {
        return 'fake';
    }

    public function charge(PaymentInstruction $instruction): PaymentResult
    {
        return PaymentResult::succeeded('fake_'.$instruction->paymentId);
    }

    public function refund(RefundInstruction $instruction): RefundResult
    {
        if ($this->refundsShouldFail) {
            return RefundResult::failed('De fake betaalprovider kon de terugbetaling niet verwerken.');
        }

        return RefundResult::succeeded('fake_refund_'.$instruction->idempotencyKey);
    }
}
