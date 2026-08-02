<?php

declare(strict_types=1);

namespace App\Tests\Unit\Pricing\Application\Dto;

use App\Catalog\Domain\Entity\CardSeries;
use App\Catalog\Domain\Entity\CardSet;
use App\Catalog\Domain\Entity\CardSetStatus;
use App\Catalog\Domain\Entity\CatalogCard;
use App\Pricing\Application\Dto\CardPriceHistoryResponse;
use App\Pricing\Application\PriceRefreshStatus;
use App\Pricing\Domain\Entity\PriceSnapshot;
use App\Pricing\Domain\Entity\PriceVariant;
use App\Shared\Domain\ValueObject\Money;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class CardPriceHistoryResponseTest extends TestCase
{
    public function testItDoesNotPresentHistoricalPricesWhenTodayHasNoData(): void
    {
        // Arrange
        $card = $this->card();
        $today = new DateTimeImmutable('2026-08-02');
        $response = new CardPriceHistoryResponse('card-id', [
            PriceSnapshot::unavailable(
                $card,
                'tcgdex',
                'cardmarket',
                'EUR',
                $today->setTime(12, 0),
                $today,
            ),
            $this->snapshot($card, PriceVariant::Standard, $today->modify('-1 day'), 15),
        ], PriceRefreshStatus::NoData);

        // Act
        $data = $response->toArray();

        // Assert
        self::assertSame([], $data['prices']);
        self::assertCount(1, $data['history']);
    }

    public function testItOnlyPresentsVariantsMeasuredOnTheLatestDay(): void
    {
        // Arrange
        $card = $this->card();
        $today = new DateTimeImmutable('2026-08-02');
        $response = new CardPriceHistoryResponse('card-id', [
            $this->snapshot($card, PriceVariant::Standard, $today, 15),
            $this->snapshot($card, PriceVariant::Foil, $today->modify('-1 day'), 32),
            $this->snapshot($card, PriceVariant::Standard, $today->modify('-1 day'), 12),
        ], PriceRefreshStatus::Refreshed);

        // Act
        $data = $response->toArray();

        // Assert
        self::assertCount(1, $data['prices']);
        self::assertSame('standard', $data['prices'][0]['variant'] ?? null);
        self::assertSame(3, $data['prices'][0]['change']['amount'] ?? null);
        self::assertCount(3, $data['history']);
    }

    private function snapshot(
        CatalogCard $card,
        PriceVariant $variant,
        DateTimeImmutable $day,
        int $trend,
    ): PriceSnapshot {
        return new PriceSnapshot(
            catalogCard: $card,
            source: 'tcgdex',
            market: 'cardmarket',
            variant: $variant,
            currency: 'EUR',
            lowest: new Money(2, 'EUR'),
            average: new Money(11, 'EUR'),
            trend: new Money($trend, 'EUR'),
            averageOneDay: null,
            averageSevenDays: null,
            averageThirtyDays: null,
            sourceUpdatedAt: $day->setTime(8, 0),
            observedAt: $day->setTime(12, 0),
            observedOn: $day,
        );
    }

    private function card(): CatalogCard
    {
        $series = new CardSeries('Sword & Shield');
        $set = new CardSet(
            $series,
            'Darkness Ablaze',
            'swsh3',
            'en',
            189,
            201,
            CardSetStatus::Active,
        );

        return new CatalogCard($set, 'Furret', '136/189', 'Uncommon', 'Pokemon');
    }
}
