<?php

declare(strict_types=1);

namespace App\Pricing\Domain\Provider;

interface MarketPriceProvider
{
    public function fetch(string $language, string $externalCardId): MarketPriceResult;
}
