<?php

declare(strict_types=1);

namespace App\Pricing\Domain\Provider;

use App\Pricing\Domain\Entity\PriceVariant;
use App\Shared\Domain\ValueObject\Money;
use InvalidArgumentException;

final readonly class MarketPriceQuote
{
    public function __construct(
        public PriceVariant $variant,
        public ?Money $lowest,
        public ?Money $average,
        public ?Money $trend,
        public ?Money $averageOneDay,
        public ?Money $averageSevenDays,
        public ?Money $averageThirtyDays,
    ) {
        if ($variant === PriceVariant::Unavailable) {
            throw new InvalidArgumentException('A market quote requires an available price variant.');
        }

        $prices = [$lowest, $average, $trend, $averageOneDay, $averageSevenDays, $averageThirtyDays];
        if (!array_any($prices, static fn (?Money $price): bool => $price instanceof Money)) {
            throw new InvalidArgumentException('A market quote must contain at least one price.');
        }

        $currencies = array_unique(array_map(
            static fn (Money $price): string => $price->currency,
            array_values(array_filter($prices, static fn (?Money $price): bool => $price instanceof Money)),
        ));
        if (count($currencies) !== 1) {
            throw new InvalidArgumentException('All prices in a market quote must use the same currency.');
        }
    }
}
