<?php

declare(strict_types=1);

namespace App\Order\Domain\ValueObject;

use App\Order\Domain\Exception\InvalidOrder;

final readonly class CancellationReason
{
    public string $value;

    public function __construct(string $value)
    {
        $value = trim($value);
        if ($value === '' || mb_strlen($value) < 3 || mb_strlen($value) > 500) {
            throw new InvalidOrder('Een annuleringsreden moet uit 3 tot 500 tekens bestaan.');
        }

        $this->value = $value;
    }
}
