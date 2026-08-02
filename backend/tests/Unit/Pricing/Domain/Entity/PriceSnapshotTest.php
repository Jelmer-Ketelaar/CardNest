<?php

declare(strict_types=1);

namespace App\Tests\Unit\Pricing\Domain\Entity;

use App\Catalog\Domain\Entity\CardSeries;
use App\Catalog\Domain\Entity\CardSet;
use App\Catalog\Domain\Entity\CardSetStatus;
use App\Catalog\Domain\Entity\CatalogCard;
use App\Pricing\Domain\Entity\PriceSnapshot;
use App\Pricing\Domain\Entity\PriceVariant;
use App\Shared\Domain\ValueObject\Money;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class PriceSnapshotTest extends TestCase
{
    public function testItStoresExactMinorUnitsAndSourceTimes(): void
    {
        // Arrange
        $sourceUpdatedAt = new DateTimeImmutable('2026-08-02T08:03:04+00:00');
        $observedAt = new DateTimeImmutable('2026-08-02T10:05:00+00:00');

        // Act
        $snapshot = $this->snapshot(
            PriceVariant::Standard,
            new Money(2, 'EUR'),
            new Money(15, 'EUR'),
            $sourceUpdatedAt,
            $observedAt,
        );

        // Assert
        self::assertSame(2, $snapshot->getLowest()?->amount);
        self::assertSame(15, $snapshot->getTrend()?->amount);
        self::assertSame($sourceUpdatedAt, $snapshot->getSourceUpdatedAt());
        self::assertSame($observedAt, $snapshot->getObservedAt());
    }

    public function testAvailableSnapshotRequiresAPrice(): void
    {
        // Arrange
        $card = $this->card();
        $observedAt = new DateTimeImmutable('2026-08-02T10:05:00+00:00');

        // Assert
        $this->expectException(InvalidArgumentException::class);

        // Act
        new PriceSnapshot(
            $card,
            'tcgdex',
            'cardmarket',
            PriceVariant::Standard,
            'EUR',
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            $observedAt,
            new DateTimeImmutable('2026-08-02'),
        );
    }

    private function snapshot(
        PriceVariant $variant,
        ?Money $lowest,
        ?Money $trend,
        DateTimeImmutable $sourceUpdatedAt,
        DateTimeImmutable $observedAt,
    ): PriceSnapshot {
        return new PriceSnapshot(
            catalogCard: $this->card(),
            source: 'tcgdex',
            market: 'cardmarket',
            variant: $variant,
            currency: 'EUR',
            lowest: $lowest,
            average: null,
            trend: $trend,
            averageOneDay: null,
            averageSevenDays: null,
            averageThirtyDays: null,
            sourceUpdatedAt: $sourceUpdatedAt,
            observedAt: $observedAt,
            observedOn: new DateTimeImmutable('2026-08-02'),
        );
    }

    private function card(): CatalogCard
    {
        $series = new CardSeries('Sword & Shield');
        $set = new CardSet(
            series: $series,
            name: 'Darkness Ablaze',
            code: 'swsh3',
            language: 'en',
            printedTotal: 189,
            total: 201,
            status: CardSetStatus::Active,
        );

        return new CatalogCard($set, 'Furret', '136/189', 'Uncommon', 'Pokemon');
    }
}
