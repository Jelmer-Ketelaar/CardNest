<?php

declare(strict_types=1);

namespace App\Tests\Unit\Collection\Domain\Entity;

use App\Catalog\Domain\Entity\CardSeries;
use App\Catalog\Domain\Entity\CardSet;
use App\Catalog\Domain\Entity\CardSetStatus;
use App\Catalog\Domain\Entity\CardVariant;
use App\Catalog\Domain\Entity\CatalogCard;
use App\Catalog\Domain\Entity\VariantCode;
use App\Collection\Domain\Entity\CardCondition;
use App\Collection\Domain\Entity\CardLanguage;
use App\Collection\Domain\Entity\CollectionItem;
use App\Collection\Domain\Entity\CollectionItemStatus;
use App\Collection\Domain\Exception\ArchivedCollectionItemCannotBeChanged;
use App\Collection\Domain\Exception\CardVariantDoesNotBelongToCard;
use App\Identity\Domain\Entity\User;
use App\Shared\Domain\ValueObject\Money;
use PHPUnit\Framework\TestCase;

final class CollectionItemTest extends TestCase
{
    public function testRepresentsOnePhysicalCardAndItsPurchaseData(): void
    {
        // Arrange
        [$card, $variant] = $this->createCard('Mudkip', '19/25');
        $owner = new User('owner@example.com', 'hash');

        // Act
        $item = new CollectionItem(
            owner: $owner,
            catalogCard: $card,
            variant: $variant,
            language: CardLanguage::English,
            condition: CardCondition::NearMint,
            purchasePrice: new Money(350, 'EUR'),
            sellerName: 'Card shop',
            notes: '  Clean copy  ',
        );

        // Assert
        $purchasePrice = $item->getPurchasePrice();
        self::assertNotNull($purchasePrice);
        self::assertSame(350, $purchasePrice->amount);
        self::assertSame('EUR', $purchasePrice->currency);
        self::assertSame('Card shop', $item->getSellerName());
        self::assertSame('Clean copy', $item->getNotes());
        self::assertSame(CollectionItemStatus::InCollection, $item->getStatus());
    }

    public function testRejectsVariantFromAnotherCatalogCard(): void
    {
        // Arrange
        [$card] = $this->createCard('Mudkip', '19/25');
        [, $otherVariant] = $this->createCard('Pikachu', '25/25');
        $this->expectException(CardVariantDoesNotBelongToCard::class);

        // Act
        new CollectionItem(
            owner: new User('owner@example.com', 'hash'),
            catalogCard: $card,
            variant: $otherVariant,
            language: CardLanguage::English,
            condition: CardCondition::NearMint,
        );

        // Assert
        // The expected domain exception is the observable result.
    }

    public function testArchivedItemCannotBeChanged(): void
    {
        // Arrange
        [$card, $variant] = $this->createCard('Mudkip', '19/25');
        $item = new CollectionItem(
            owner: new User('owner@example.com', 'hash'),
            catalogCard: $card,
            variant: $variant,
            language: CardLanguage::English,
            condition: CardCondition::NearMint,
        );
        $item->archive();
        $this->expectException(ArchivedCollectionItemCannotBeChanged::class);

        // Act
        $item->changeCondition(CardCondition::Mint);

        // Assert
        // The expected domain exception is the observable result.
    }

    /** @return array{CatalogCard, CardVariant} */
    private function createCard(string $name, string $number): array
    {
        $series = new CardSeries('Series '.$name);
        $set = new CardSet(
            series: $series,
            name: 'Set '.$name,
            code: 'set-'.mb_strtolower($name),
            language: 'en',
            printedTotal: 25,
            total: 25,
            status: CardSetStatus::Active,
        );
        $card = new CatalogCard($set, $name, $number, 'Promo', 'Collectible');

        return [$card, $card->addVariant(VariantCode::Holo)];
    }
}
