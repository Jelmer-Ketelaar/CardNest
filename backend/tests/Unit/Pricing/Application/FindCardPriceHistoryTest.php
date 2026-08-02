<?php

declare(strict_types=1);

namespace App\Tests\Unit\Pricing\Application;

use App\Catalog\Domain\Entity\CardSeries;
use App\Catalog\Domain\Entity\CardSet;
use App\Catalog\Domain\Entity\CardSetStatus;
use App\Catalog\Domain\Entity\CatalogCard;
use App\Catalog\Domain\Repository\CatalogCardRepository;
use App\Pricing\Application\FindCardPriceHistory;
use App\Pricing\Application\RefreshCardPrices;
use App\Pricing\Domain\Provider\MarketPriceProvider;
use App\Pricing\Domain\Provider\PriceProviderException;
use App\Pricing\Domain\Repository\PriceSnapshotRepository;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore;

final class FindCardPriceHistoryTest extends TestCase
{
    public function testItUsesAFiveMinuteCooldownAfterAProviderFailure(): void
    {
        // Arrange
        $card = $this->card();
        $card->addExternalReference('tcgdex', 'en:swsh3-136');
        $catalogCards = $this->createMock(CatalogCardRepository::class);
        $catalogCards->expects(self::exactly(3))->method('get')->with('card-id')->willReturn($card);
        $snapshots = $this->createMock(PriceSnapshotRepository::class);
        $snapshots->expects(self::once())->method('hasObservationOn')->willReturn(false);
        $snapshots->expects(self::exactly(2))->method('history')->willReturn([]);
        $provider = $this->createMock(MarketPriceProvider::class);
        $provider->expects(self::once())
            ->method('fetch')
            ->willThrowException(new PriceProviderException('Provider unavailable.'));
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('warning');
        $refresher = new RefreshCardPrices(
            $catalogCards,
            $snapshots,
            $provider,
            new LockFactory(new InMemoryStore()),
        );
        $finder = new FindCardPriceHistory($catalogCards, $snapshots, $refresher, $logger, new ArrayAdapter());

        // Act
        $first = $finder->find('card-id', 30)?->toArray();
        $second = $finder->find('card-id', 30)?->toArray();

        // Assert
        self::assertSame('refresh_failed', $first['refreshStatus'] ?? null);
        self::assertSame('refresh_failed', $second['refreshStatus'] ?? null);
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
