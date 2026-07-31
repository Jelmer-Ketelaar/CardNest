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
