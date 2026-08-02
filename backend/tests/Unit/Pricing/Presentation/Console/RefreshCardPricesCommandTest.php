<?php

declare(strict_types=1);

namespace App\Tests\Unit\Pricing\Presentation\Console;

use App\Catalog\Domain\Entity\CardSeries;
use App\Catalog\Domain\Entity\CardSet;
use App\Catalog\Domain\Entity\CardSetStatus;
use App\Catalog\Domain\Entity\CatalogCard;
use App\Catalog\Domain\Repository\CatalogCardRepository;
use App\Pricing\Application\RefreshCardPrices;
use App\Pricing\Domain\Provider\MarketPriceProvider;
use App\Pricing\Domain\Provider\MarketPriceResult;
use App\Pricing\Domain\Provider\PriceProviderException;
use App\Pricing\Domain\Repository\PriceSnapshotRepository;
use App\Pricing\Domain\Repository\TrackedPriceCardRepository;
use App\Pricing\Presentation\Console\RefreshCardPricesCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore;

final class RefreshCardPricesCommandTest extends TestCase
{
    public function testItContinuesRefreshingTrackedCardsAfterOneProviderFailure(): void
    {
        // Arrange
        $first = $this->card('1/2');
        $second = $this->card('2/2');
        $first->addExternalReference('tcgdex', 'en:first-card');
        $second->addExternalReference('tcgdex', 'en:second-card');
        $catalogCards = $this->createMock(CatalogCardRepository::class);
        $catalogCards->expects(self::exactly(2))
            ->method('get')
            ->willReturnMap([
                ['first', $first],
                ['second', $second],
            ]);
        $snapshots = $this->createMock(PriceSnapshotRepository::class);
        $snapshots->expects(self::exactly(2))->method('hasObservationOn')->willReturn(false);
        $snapshots->expects(self::once())->method('replaceDaily');
        $provider = $this->createMock(MarketPriceProvider::class);
        $provider->expects(self::exactly(2))
            ->method('fetch')
            ->willReturnCallback(static function (string $language, string $externalId): MarketPriceResult {
                if ($externalId === 'first-card') {
                    throw new PriceProviderException('Temporary source failure.');
                }

                return new MarketPriceResult('tcgdex', 'cardmarket', 'EUR', [], null);
            });
        $trackedCards = $this->createMock(TrackedPriceCardRepository::class);
        $trackedCards->expects(self::once())->method('cardIds')->willReturn(['first', 'second']);
        $refresher = new RefreshCardPrices(
            $catalogCards,
            $snapshots,
            $provider,
            new LockFactory(new InMemoryStore()),
        );
        $tester = new CommandTester(new RefreshCardPricesCommand($refresher, $trackedCards));

        // Act
        $exitCode = $tester->execute([]);

        // Assert
        self::assertSame(Command::FAILURE, $exitCode);
        self::assertStringContainsString('refresh_failed=1', $tester->getDisplay());
        self::assertStringContainsString('no_data=1', $tester->getDisplay());
    }

    private function card(string $number): CatalogCard
    {
        $series = new CardSeries('Command test');
        $set = new CardSet(
            $series,
            'Command set '.$number,
            'command-'.$number,
            'en',
            2,
            2,
            CardSetStatus::Active,
        );

        return new CatalogCard($set, 'Card '.$number, $number, 'Common', 'Pokemon');
    }
}
