<?php

declare(strict_types=1);

namespace App\Payment\Domain\Entity;

enum PaymentFinalizationStatus: string
{
    case NotRequired = 'not_required';
    case Pending = 'pending';
    case Finalized = 'finalized';
    case Released = 'released';
}
