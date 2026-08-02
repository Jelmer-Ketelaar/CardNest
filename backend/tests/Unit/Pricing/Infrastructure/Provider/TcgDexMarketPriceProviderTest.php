<?php

declare(strict_types=1);

namespace App\Tests\Unit\Pricing\Infrastructure\Provider;

use App\Catalog\Domain\Provider\CardCatalogProvider;
use App\Catalog\Domain\Provider\ImportedCatalogCard;
use App\Pricing\Domain\Entity\PriceVariant;
use App\Pricing\Domain\Provider\PriceProviderException;
use App\Pricing\Infrastructure\Provider\TcgDexMarketPriceProvider;
use PHPUnit\Framework\TestCase;

final class TcgDexMarketPriceProviderTest extends TestCase
{
    public function testItNormalizesDocumentedCardmarketPricesToEuroCents(): void
    {
        // Arrange
        $catalogProvider = $this->createMock(CardCatalogProvider::class);
        $catalogProvider->expects(self::once())
            ->method('findCard')
            ->with('en', 'swsh3-136')
            ->willReturn($this->importedCard([
                'pricing' => [
                    'cardmarket' => [
                        'updated' => '2026-08-02T08:03:04.747Z',
                        'unit' => 'EUR',
                        'avg' => 0.11,
                        'low' => 0.02,
                        'trend' => 0.15,
                        'avg1' => 0.05,
                        'avg7' => 0.13,
                        'avg30' => 0.11,
                        'avg-holo' => 0.29,
                        'low-holo' => 0.04,
                        'trend-holo' => 0.32,
                    ],
                ],
            ]));
        $provider = new TcgDexMarketPriceProvider($catalogProvider);

        // Act
        $result = $provider->fetch('en', 'swsh3-136');

        // Assert
        self::assertSame('tcgdex', $result->source);
        self::assertSame('cardmarket', $result->market);
        self::assertSame('EUR', $result->currency);
        self::assertCount(2, $result->quotes);
        self::assertSame(PriceVariant::Standard, $result->quotes[0]->variant);
        self::assertSame(2, $result->quotes[0]->lowest?->amount);
        self::assertSame(15, $result->quotes[0]->trend?->amount);
        self::assertSame(PriceVariant::Foil, $result->quotes[1]->variant);
        self::assertSame(29, $result->quotes[1]->average?->amount);
        self::assertSame('2026-08-02T08:03:04+00:00', $result->sourceUpdatedAt?->format(DATE_ATOM));
    }

    public function testItMapsDetailedVariantPricingWithoutLosingFoilPrices(): void
    {
        // Arrange
        $catalogProvider = $this->createMock(CardCatalogProvider::class);
        $catalogProvider->expects(self::exactly(2))
            ->method('findCard')
            ->willReturnOnConsecutiveCalls(
                $this->importedCard([
                    'variants_detailed' => [
                        [
                            'type' => 'normal',
                            'pricing' => ['cardmarket' => ['unit' => 'EUR', 'trend' => 1.239]],
                        ],
                        [
                            'type' => 'holo',
                            'pricing' => ['cardmarket' => ['unit' => 'EUR', 'trend' => 2.349]],
                        ],
                    ],
                ]),
                $this->importedCard([]),
            );
        $provider = new TcgDexMarketPriceProvider($catalogProvider);

        // Act
        $nested = $provider->fetch('en', 'nested-card');
        $missing = $provider->fetch('en', 'missing-card');

        // Assert
        self::assertCount(2, $nested->quotes);
        self::assertSame(PriceVariant::Standard, $nested->quotes[0]->variant);
        self::assertSame(124, $nested->quotes[0]->trend?->amount);
        self::assertSame(PriceVariant::Foil, $nested->quotes[1]->variant);
        self::assertSame(235, $nested->quotes[1]->trend?->amount);
        self::assertSame([], $missing->quotes);
    }

    public function testItRejectsNonEuroCardmarketData(): void
    {
        // Arrange
        $catalogProvider = $this->createMock(CardCatalogProvider::class);
        $catalogProvider->expects(self::once())
            ->method('findCard')
            ->willReturn($this->importedCard([
                'pricing' => ['cardmarket' => ['unit' => 'USD', 'trend' => 1.25]],
            ]));
        $provider = new TcgDexMarketPriceProvider($catalogProvider);

        // Assert
        $this->expectException(PriceProviderException::class);

        // Act
        $provider->fetch('en', 'wrong-currency');
    }

    /** @param array<string, mixed> $rawData */
    private function importedCard(array $rawData): ImportedCatalogCard
    {
        return new ImportedCatalogCard(
            language: 'en',
            externalId: 'swsh3-136',
            setExternalId: 'swsh3',
            name: 'Furret',
            number: '136/189',
            totalNumberInSet: '189',
            rarity: 'Uncommon',
            cardType: 'Pokemon',
            primaryCollectible: 'Furret',
            illustrator: null,
            imageUrl: null,
            thumbnailUrl: null,
            releaseDate: null,
            variants: [],
            metadata: [],
            rawData: $rawData,
            isDetailed: true,
        );
    }
}
