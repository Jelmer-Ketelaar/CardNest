<?php

declare(strict_types=1);

namespace App\Payment\Domain\Entity;

enum PaymentTransactionStatus: string
{
    case Succeeded = 'succeeded';
    case Failed = 'failed';
}
