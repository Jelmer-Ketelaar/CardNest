<?php

declare(strict_types=1);

namespace App\Payment\Domain\Entity;

enum PaymentTransactionType: string
{
    case Charge = 'charge';
    case Refund = 'refund';
}
