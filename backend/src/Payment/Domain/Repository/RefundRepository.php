<?php

declare(strict_types=1);

namespace App\Payment\Domain\Repository;

use App\Payment\Domain\Entity\Refund;

interface RefundRepository
{
    public function add(Refund $refund): void;
}
