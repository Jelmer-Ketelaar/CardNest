<?php

declare(strict_types=1);

namespace App\Tests\Unit\Wishlist\Domain\Entity;

use App\Catalog\Domain\Entity\CardSeries;
use App\Catalog\Domain\Entity\CardSet;
use App\Catalog\Domain\Entity\CardSetStatus;
use App\Catalog\Domain\Entity\CatalogCard;
use App\Catalog\Domain\Entity\VariantCode;
use App\Collection\Domain\Entity\CardCondition;
use App\Collection\Domain\Entity\CardLanguage;
use App\Collection\Domain\Entity\Collection;
use App\Collection\Domain\Entity\CollectionItem;
use App\Identity\Domain\Entity\User;
use App\Shared\Domain\ValueObject\Money;
use App\Wishlist\Domain\Entity\Wishlist;
use App\Wishlist\Domain\Entity\WishlistItem;
use App\Wishlist\Domain\Entity\WishlistPriority;
use App\Wishlist\Domain\Exception\InvalidWishlistItem;
use PHPUnit\Framework\TestCase;

final class WishlistItemTest extends TestCase
{
    public function testMatchesEveryCriterionAndCapsFulfillmentAtDesiredQuantity(): void
    {
        // Arrange
        [$owner, $card, $variant] = $this->references();
        $wishlist = new Wishlist($owner, 'Master set');
        $item = new WishlistItem(
            $wishlist,
            $card,
            CardLanguage::English,
            $variant,
            CardCondition::Excellent,
            new Money(500, 'EUR'),
            2,
            WishlistPriority::Wanted,
        );
        $wishlist->addItem($item);
        $matching = new CollectionItem(
            new Collection($owner),
            $card,
            $variant,
            CardLanguage::English,
            CardCondition::NearMint,
            new Money(450, 'EUR'),
        );

        // Act
        $matches = $item->matches($matching);
        $item->fulfill(5);

        // Assert
        self::assertTrue($matches);
        self::assertTrue($item->isCompleted());
        self::assertSame(2, $item->getFulfilledQuantity());
        self::assertSame(0, $item->getRemainingQuantity());
        self::assertNotNull($item->getCompletedAt());
        self::assertFalse($item->matches($matching));
    }

    public function testRejectsCardsBelowMinimumConditionOrAboveMaximumPrice(): void
    {
        // Arrange
        [$owner, $card, $variant] = $this->references();
        $wishlist = new Wishlist($owner, 'Mudkip');
        $item = new WishlistItem(
            $wishlist,
            $card,
            CardLanguage::English,
            $variant,
            CardCondition::Excellent,
            new Money(500, 'EUR'),
            1,
        );
        $badCondition = new CollectionItem(
            new Collection($owner),
            $card,
            $variant,
            CardLanguage::English,
            CardCondition::Good,
            new Money(400, 'EUR'),
        );
        $tooExpensive = new CollectionItem(
            new Collection($owner),
            $card,
            $variant,
            CardLanguage::English,
            CardCondition::Mint,
            new Money(501, 'EUR'),
        );
        $unknownPrice = new CollectionItem(
            new Collection($owner),
            $card,
            $variant,
            CardLanguage::English,
            CardCondition::Mint,
        );

        // Act & Assert
        self::assertFalse($item->matches($badCondition));
        self::assertFalse($item->matches($tooExpensive));
        self::assertFalse($item->matches($unknownPrice));
        self::assertTrue(CardCondition::Mint->meetsMinimum(CardCondition::Excellent));
        self::assertFalse(CardCondition::Played->meetsMinimum(CardCondition::Excellent));
    }

    public function testRejectsVariantThatDoesNotBelongToCard(): void
    {
        // Arrange
        [$owner, $card] = $this->references();
        $otherCard = new CatalogCard($card->getSet(), 'Pikachu', '25/25', 'Promo', 'Collectible');
        $otherVariant = $otherCard->addVariant(VariantCode::Normal);
        $this->expectException(InvalidWishlistItem::class);

        // Act
        new WishlistItem(
            new Wishlist($owner, 'Invalid'),
            $card,
            CardLanguage::English,
            $otherVariant,
            null,
            null,
            1,
        );

        // Assert
        // The expected domain exception is the observable result.
    }

    public function testUpdatingPreferencesNormalizesNotesAndSynchronizesCompletion(): void
    {
        // Arrange
        [$owner, $card, $variant] = $this->references();
        $wishlist = new Wishlist($owner, 'Mudkip');
        $item = new WishlistItem(
            $wishlist,
            $card,
            CardLanguage::English,
            $variant,
            CardCondition::Excellent,
            new Money(500, 'EUR'),
            2,
        );
        $wishlist->addItem($item);
        $item->fulfill(2);

        // Act
        $item->updatePreferences(
            null,
            null,
            null,
            null,
            3,
            WishlistPriority::High,
            '  Any clean Mudkip  ',
        );
        $item->synchronizeFulfillment(1);

        // Assert
        self::assertNull($item->getDesiredLanguage());
        self::assertNull($item->getDesiredVariant());
        self::assertNull($item->getMinimumCondition());
        self::assertNull($item->getMaxPrice());
        self::assertSame(3, $item->getDesiredQuantity());
        self::assertSame(1, $item->getFulfilledQuantity());
        self::assertSame(2, $item->getRemainingQuantity());
        self::assertFalse($item->isCompleted());
        self::assertNull($item->getCompletedAt());
        self::assertSame(WishlistPriority::High, $item->getPriority());
        self::assertSame('Any clean Mudkip', $item->getNotes());
    }

    /** @return array{User, CatalogCard, \App\Catalog\Domain\Entity\CardVariant} */
    private function references(): array
    {
        $owner = new User('owner@example.com', 'hash');
        $series = new CardSeries('Promos');
        $set = new CardSet($series, 'Promo set', 'promo', 'en', 25, 25, status: CardSetStatus::Active);
        $card = new CatalogCard($set, 'Mudkip', '19/25', 'Promo', 'Collectible');

        return [$owner, $card, $card->addVariant(VariantCode::Holo)];
    }
}
