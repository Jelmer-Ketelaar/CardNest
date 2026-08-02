<?php

declare(strict_types=1);

namespace App\Tests\Unit\Pricing\Application;

use App\Catalog\Domain\Entity\CardSeries;
use App\Catalog\Domain\Entity\CardSet;
use App\Catalog\Domain\Entity\CardSetStatus;
use App\Catalog\Domain\Entity\CatalogCard;
use App\Catalog\Domain\Repository\CatalogCardRepository;
use App\Pricing\Application\PriceRefreshStatus;
use App\Pricing\Application\RefreshCardPrices;
use App\Pricing\Domain\Entity\PriceSnapshot;
use App\Pricing\Domain\Entity\PriceVariant;
use App\Pricing\Domain\Provider\MarketPriceProvider;
use App\Pricing\Domain\Provider\MarketPriceQuote;
use App\Pricing\Domain\Provider\MarketPriceResult;
use App\Pricing\Domain\Repository\PriceSnapshotRepository;
use App\Shared\Domain\ValueObject\Money;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore;

final class RefreshCardPricesTest extends TestCase
{
    public function testItDoesNotFetchTheSameCardTwiceOnAnAmsterdamCalendarDay(): void
    {
        // Arrange
        $card = $this->card();
        $catalogCards = $this->createMock(CatalogCardRepository::class);
        $catalogCards->expects(self::once())->method('get')->with('card-id')->willReturn($card);
        $snapshots = $this->createMock(PriceSnapshotRepository::class);
        $snapshots->expects(self::once())
            ->method('hasObservationOn')
            ->with(
                $card,
                'tcgdex',
                'cardmarket',
                self::callback(static fn (DateTimeImmutable $day): bool => '2026-08-03' === $day->format('Y-m-d')),
            )
            ->willReturn(true);
        $snapshots->expects(self::never())->method('replaceDaily');
        $provider = $this->createMock(MarketPriceProvider::class);
        $provider->expects(self::never())->method('fetch');
        $refresher = new RefreshCardPrices($catalogCards, $snapshots, $provider, $this->lockFactory());

        // Act
        $status = $refresher->refresh('card-id', now: new DateTimeImmutable('2026-08-02T22:30:00+00:00'));

        // Assert
        self::assertSame(PriceRefreshStatus::Current, $status);
    }

    public function testItStoresProviderPricesAsOneDailySnapshotPerVariant(): void
    {
        // Arrange
        $card = $this->card();
        $card->addExternalReference('tcgdex', 'en:swsh3-136');
        $catalogCards = $this->createMock(CatalogCardRepository::class);
        $catalogCards->expects(self::once())->method('get')->with('card-id')->willReturn($card);
        $snapshots = $this->createMock(PriceSnapshotRepository::class);
        $snapshots->expects(self::once())->method('hasObservationOn')->willReturn(false);
        $snapshots->expects(self::once())
            ->method('replaceDaily')
            ->with(
                $card,
                'tcgdex',
                'cardmarket',
                self::callback(static fn (DateTimeImmutable $day): bool => '2026-08-02' === $day->format('Y-m-d')),
                self::callback(static function (array $dailySnapshots): bool {
                    $snapshot = $dailySnapshots[0] ?? null;

                    return 1 === count($dailySnapshots)
                        && $snapshot instanceof PriceSnapshot
                        && PriceVariant::Standard === $snapshot->getVariant()
                        && 2 === $snapshot->getLowest()?->amount
                        && 15 === $snapshot->getTrend()?->amount;
                }),
            );
        $provider = $this->createMock(MarketPriceProvider::class);
        $provider->expects(self::once())
            ->method('fetch')
            ->with('en', 'swsh3-136')
            ->willReturn(new MarketPriceResult(
                'tcgdex',
                'cardmarket',
                'EUR',
                [new MarketPriceQuote(
                    PriceVariant::Standard,
                    new Money(2, 'EUR'),
                    new Money(11, 'EUR'),
                    new Money(15, 'EUR'),
                    null,
                    null,
                    null,
                )],
                new DateTimeImmutable('2026-08-02T08:03:04+00:00'),
            ));
        $refresher = new RefreshCardPrices($catalogCards, $snapshots, $provider, $this->lockFactory());

        // Act
        $status = $refresher->refresh('card-id', now: new DateTimeImmutable('2026-08-02T10:05:00+00:00'));

        // Assert
        self::assertSame(PriceRefreshStatus::Refreshed, $status);
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

    private function lockFactory(): LockFactory
    {
        return new LockFactory(new InMemoryStore());
    }
}
