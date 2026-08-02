<?php

declare(strict_types=1);

namespace App\Pricing\Domain\Repository;

interface TrackedPriceCardRepository
{
    /** @return iterable<string> */
    public function cardIds(): iterable;
}
