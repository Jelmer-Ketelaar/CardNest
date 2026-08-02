<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Domain\Entity;

use App\Catalog\Domain\Entity\CardSeries;
use App\Catalog\Domain\Entity\CardSet;
use App\Catalog\Domain\Entity\CardSetStatus;
use App\Catalog\Domain\Entity\CatalogCard;
use App\Catalog\Domain\Entity\VariantCode;
use DateTimeImmutable;
use LogicException;
use PHPUnit\Framework\TestCase;

final class CatalogCardTest extends TestCase
{
    public function testCannotRegisterTheSameVariantTwice(): void
    {
        // Arrange
        $card = $this->createCard();
        $card->addVariant(VariantCode::Holo);
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Variant "holo" is already registered for this card.');

        // Act
        $card->addVariant(VariantCode::Holo);

        // Assert
        // The expected domain exception is the observable result.
    }

    public function testCannotRegisterTheSameExternalReferenceTwice(): void
    {
        // Arrange
        $card = $this->createCard();
        $card->addExternalReference('catalog-provider', 'card-19');
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(
            'External reference "catalog-provider:card-19" is already registered for this card.',
        );

        // Act
        $card->addExternalReference('catalog-provider', 'card-19');

        // Assert
        // The expected domain exception is the observable result.
    }

    public function testSynchronizationKeepsManualCorrectionsSeparateFromRawData(): void
    {
        // Arrange
        $reference = $this->createCard()->addExternalReference(
            'catalog-provider',
            'card-19',
            ['name' => 'Mudkip'],
        );
        $reference->applyManualCorrection(['name' => 'Mudkip corrected']);
        $syncedAt = new DateTimeImmutable('2026-07-31T12:00:00+00:00');

        // Act
        $reference->markSynced(['name' => 'Mudkip from provider'], $syncedAt);

        // Assert
        self::assertSame(['name' => 'Mudkip from provider'], $reference->getRawData());
        self::assertSame(['name' => 'Mudkip corrected'], $reference->getCorrectedData());
        self::assertSame($syncedAt, $reference->getLastSyncedAt());
    }

    public function testDetailedProviderSynchronizationEnrichesFieldsAndOnlyAddsVariants(): void
    {
        // Arrange
        $card = $this->createCard();
        $card->addVariant(VariantCode::Normal);
        $syncedAt = new DateTimeImmutable('2026-08-02T12:00:00+00:00');

        // Act
        $card->synchronizeFromProvider(
            name: 'Mudkip',
            rarity: 'Rare Holo',
            cardType: 'Pokemon',
            primaryCollectible: 'Mudkip',
            illustrator: 'Yuka Morii',
            imageUrl: 'https://assets.example.test/mudkip',
            thumbnailUrl: null,
            variants: [VariantCode::Normal, VariantCode::Holo],
            metadata: ['sourceDetailLevel' => 'detail'],
            syncedAt: $syncedAt,
        );

        // Assert
        self::assertSame('Rare Holo', $card->getRarity());
        self::assertSame('Pokemon', $card->getCardType());
        self::assertSame('Yuka Morii', $card->getIllustrator());
        self::assertSame('https://assets.example.test/mudkip', $card->getImageUrl());
        self::assertSame(['sourceDetailLevel' => 'detail'], $card->getMetadata());
        self::assertSame(['normal', 'holo'], array_map(
            static fn (\App\Catalog\Domain\Entity\CardVariant $variant): string => $variant->getCode()->value,
            $card->getVariants()->toArray(),
        ));
        self::assertSame($syncedAt, $card->getUpdatedAt());
    }

    public function testSummarySynchronizationAddsImagesWithoutRemovingDetailedData(): void
    {
        // Arrange
        $card = $this->createCard();
        $card->addVariant(VariantCode::Holo);
        $card->synchronizeFromProvider(
            name: 'Mudkip',
            rarity: 'Rare Holo',
            cardType: 'Pokemon',
            primaryCollectible: 'Mudkip',
            illustrator: 'Yuka Morii',
            imageUrl: null,
            thumbnailUrl: null,
            variants: [VariantCode::Holo],
            metadata: ['sourceDetailLevel' => 'detail'],
        );
        $syncedAt = new DateTimeImmutable('2026-08-02T14:00:00+00:00');

        // Act
        $card->synchronizeSummaryFromProvider(
            name: 'Mudkip',
            imageUrl: 'https://assets.example.test/mudkip/high.webp',
            thumbnailUrl: 'https://assets.example.test/mudkip/low.webp',
            syncedAt: $syncedAt,
        );

        // Assert
        self::assertSame('Rare Holo', $card->getRarity());
        self::assertSame('Yuka Morii', $card->getIllustrator());
        self::assertSame(['sourceDetailLevel' => 'detail'], $card->getMetadata());
        self::assertSame('https://assets.example.test/mudkip/high.webp', $card->getImageUrl());
        self::assertSame($syncedAt, $card->getUpdatedAt());
    }

    private function createCard(): CatalogCard
    {
        $series = new CardSeries('Promotional collections');
        $set = new CardSet(
            series: $series,
            name: "McDonald's Collection 2021",
            code: 'mcd21-unit',
            language: 'en',
            printedTotal: 25,
            total: 25,
            status: CardSetStatus::Active,
        );

        return new CatalogCard(
            set: $set,
            name: 'Mudkip',
            number: '19/25',
            rarity: 'Promo',
            cardType: 'Collectible',
        );
    }
}
