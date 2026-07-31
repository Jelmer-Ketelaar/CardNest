<?php

declare(strict_types=1);

namespace App\Payment\Domain\Provider;

interface PaymentProvider
{
    public function name(): string;

    public function charge(PaymentInstruction $instruction): PaymentResult;
}
