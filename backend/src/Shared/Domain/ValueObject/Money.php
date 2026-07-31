<?php

declare(strict_types=1);

namespace App\Shared\Domain\ValueObject;

use InvalidArgumentException;

final readonly class Money
{
    public function __construct(
        public int $amount,
        public string $currency,
    ) {
        if ($amount < 0) {
            throw new InvalidArgumentException('A money amount cannot be negative.');
        }

        if (1 !== preg_match('/^[A-Z]{3}$/', $currency)) {
            throw new InvalidArgumentException('A currency must be a three-letter uppercase ISO code.');
        }
    }

    /** @return array{amount: int, currency: string} */
    public function toArray(): array
    {
        return ['amount' => $this->amount, 'currency' => $this->currency];
    }
}
