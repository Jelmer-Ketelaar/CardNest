<?php

declare(strict_types=1);

namespace App\Pricing\Domain\Provider;

use DateTimeImmutable;
use InvalidArgumentException;

final readonly class MarketPriceResult
{
    /** @param list<MarketPriceQuote> $quotes */
    public function __construct(
        public string $source,
        public string $market,
        public string $currency,
        public array $quotes,
        public ?DateTimeImmutable $sourceUpdatedAt,
    ) {
        if (preg_match('/^[A-Z]{3}$/', $currency) !== 1) {
            throw new InvalidArgumentException('A market price result requires an ISO currency code.');
        }

        foreach ($quotes as $quote) {
            $prices = [
                $quote->lowest,
                $quote->average,
                $quote->trend,
                $quote->averageOneDay,
                $quote->averageSevenDays,
                $quote->averageThirtyDays,
            ];
            foreach ($prices as $price) {
                if ($price !== null && $price->currency !== $currency) {
                    throw new InvalidArgumentException('Every quote must use the result currency.');
                }
            }
        }
    }
}
