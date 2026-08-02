<?php

declare(strict_types=1);

namespace App\Pricing\Infrastructure\Provider;

use App\Catalog\Domain\Entity\CatalogLanguage;
use App\Catalog\Domain\Provider\CardCatalogProvider;
use App\Catalog\Domain\Provider\ImportedCatalogCard;
use App\Pricing\Domain\Entity\PriceVariant;
use App\Pricing\Domain\Provider\MarketPriceProvider;
use App\Pricing\Domain\Provider\MarketPriceQuote;
use App\Pricing\Domain\Provider\MarketPriceResult;
use App\Pricing\Domain\Provider\PriceProviderException;
use App\Shared\Domain\ValueObject\Money;
use DateTimeImmutable;
use Throwable;

final readonly class TcgDexMarketPriceProvider implements MarketPriceProvider
{
    private const string SOURCE = 'tcgdex';
    private const string MARKET = 'cardmarket';
    private const string CURRENCY = 'EUR';

    public function __construct(private CardCatalogProvider $catalogProvider)
    {
    }

    public function fetch(string $language, string $externalCardId): MarketPriceResult
    {
        if (CatalogLanguage::tryFrom($language) === null) {
            throw new PriceProviderException(sprintf('TCGdex language "%s" is not supported.', $language));
        }

        try {
            $card = $this->catalogProvider->findCard($language, $externalCardId);
        } catch (Throwable $exception) {
            throw new PriceProviderException('TCGdex price data could not be retrieved.', previous: $exception);
        }

        if (!$card instanceof ImportedCatalogCard) {
            return new MarketPriceResult(self::SOURCE, self::MARKET, self::CURRENCY, [], null);
        }

        $topLevel = $this->nestedObject($card->rawData, ['pricing', 'cardmarket']);
        if ($topLevel !== null) {
            return $this->pricingResult($topLevel);
        }

        return $this->detailedPricingResult($card->rawData);
    }

    /** @param array<string, mixed> $pricing */
    private function pricingResult(array $pricing): MarketPriceResult
    {
        $this->assertEuroPricing($pricing);
        $quotes = [];
        $standard = $this->quote($pricing, PriceVariant::Standard, '');
        if ($standard instanceof MarketPriceQuote) {
            $quotes[] = $standard;
        }
        $foil = $this->quote($pricing, PriceVariant::Foil, '-holo');
        if ($foil instanceof MarketPriceQuote) {
            $quotes[] = $foil;
        }

        return new MarketPriceResult(
            self::SOURCE,
            self::MARKET,
            self::CURRENCY,
            $quotes,
            $this->dateOrNull($pricing['updated'] ?? null),
        );
    }

    /** @param array<string, mixed> $rawData */
    private function detailedPricingResult(array $rawData): MarketPriceResult
    {
        $variants = $rawData['variants_detailed'] ?? null;
        if (!is_array($variants) || !array_is_list($variants)) {
            return new MarketPriceResult(self::SOURCE, self::MARKET, self::CURRENCY, [], null);
        }

        $quotesByVariant = [];
        $sourceUpdatedAt = null;
        foreach ($variants as $variant) {
            if (!is_array($variant) || array_is_list($variant)) {
                continue;
            }

            $pricing = $this->nestedObject($variant, ['pricing', 'cardmarket']);
            if ($pricing === null) {
                continue;
            }
            $this->assertEuroPricing($pricing);

            $priceVariant = match ($variant['type'] ?? null) {
                'normal' => PriceVariant::Standard,
                'cosmos-holo', 'holo', 'reverse' => PriceVariant::Foil,
                default => null,
            };
            if ($priceVariant instanceof PriceVariant) {
                $quote = $this->quote($pricing, $priceVariant, '');
                if ($quote instanceof MarketPriceQuote) {
                    $quotesByVariant[$priceVariant->value] ??= $quote;
                }
            }

            $foil = $this->quote($pricing, PriceVariant::Foil, '-holo');
            if ($foil instanceof MarketPriceQuote) {
                $quotesByVariant[PriceVariant::Foil->value] ??= $foil;
            }

            $updatedAt = $this->dateOrNull($pricing['updated'] ?? null);
            if ($updatedAt instanceof DateTimeImmutable
                && (!$sourceUpdatedAt instanceof DateTimeImmutable || $updatedAt > $sourceUpdatedAt)) {
                $sourceUpdatedAt = $updatedAt;
            }
        }

        return new MarketPriceResult(
            self::SOURCE,
            self::MARKET,
            self::CURRENCY,
            array_values($quotesByVariant),
            $sourceUpdatedAt,
        );
    }

    /** @param array<string, mixed> $pricing */
    private function assertEuroPricing(array $pricing): void
    {
        if (($pricing['unit'] ?? null) !== self::CURRENCY) {
            throw new PriceProviderException('TCGdex returned an unsupported Cardmarket currency.');
        }
    }

    /** @param array<string, mixed> $pricing */
    private function quote(array $pricing, PriceVariant $variant, string $suffix): ?MarketPriceQuote
    {
        $lowest = $this->money($pricing['low'.$suffix] ?? null);
        $average = $this->money($pricing['avg'.$suffix] ?? null);
        $trend = $this->money($pricing['trend'.$suffix] ?? null);
        $averageOneDay = $this->money($pricing['avg1'.$suffix] ?? null);
        $averageSevenDays = $this->money($pricing['avg7'.$suffix] ?? null);
        $averageThirtyDays = $this->money($pricing['avg30'.$suffix] ?? null);
        $prices = [$lowest, $average, $trend, $averageOneDay, $averageSevenDays, $averageThirtyDays];
        if (!array_any($prices, static fn (?Money $price): bool => $price instanceof Money)) {
            return null;
        }

        return new MarketPriceQuote(
            $variant,
            $lowest,
            $average,
            $trend,
            $averageOneDay,
            $averageSevenDays,
            $averageThirtyDays,
        );
    }

    private function money(mixed $value): ?Money
    {
        if (!is_int($value) && !is_float($value)) {
            return null;
        }
        if (!is_finite((float) $value) || $value < 0) {
            throw new PriceProviderException('TCGdex returned an invalid Cardmarket price.');
        }

        return new Money((int) round($value * 100, mode: PHP_ROUND_HALF_UP), self::CURRENCY);
    }

    private function dateOrNull(mixed $value): ?DateTimeImmutable
    {
        if (!is_string($value) || $value === '') {
            return null;
        }

        try {
            return new DateTimeImmutable($value);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @param array<string, mixed> $data
     * @param list<string>         $path
     *
     * @return array<string, mixed>|null
     */
    private function nestedObject(array $data, array $path): ?array
    {
        $value = $data;
        foreach ($path as $key) {
            $next = $value[$key] ?? null;
            if (!is_array($next) || array_is_list($next)) {
                return null;
            }
            $value = $next;
        }

        return $value;
    }
}
