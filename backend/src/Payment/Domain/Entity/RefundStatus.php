<?php

declare(strict_types=1);

namespace App\Payment\Domain\Entity;

enum RefundStatus: string
{
    case Pending = 'pending';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
}
