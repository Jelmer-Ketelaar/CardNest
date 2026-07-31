<?php

declare(strict_types=1);

namespace App\Tests\Unit\Marketplace\Domain\Entity;

use App\Catalog\Domain\Entity\CardSeries;
use App\Catalog\Domain\Entity\CardSet;
use App\Catalog\Domain\Entity\CardSetStatus;
use App\Catalog\Domain\Entity\CatalogCard;
use App\Catalog\Domain\Entity\VariantCode;
use App\Collection\Domain\Entity\CardCondition;
use App\Collection\Domain\Entity\CardLanguage;
use App\Collection\Domain\Entity\CollectionItem;
use App\Identity\Domain\Entity\User;
use App\Marketplace\Domain\Entity\Listing;
use App\Marketplace\Domain\Entity\ListingImageSide;
use App\Marketplace\Domain\Entity\ListingStatus;
use App\Marketplace\Domain\Entity\ShippingMethodCode;
use App\Marketplace\Domain\Exception\InvalidListing;
use App\Marketplace\Domain\ValueObject\ListingPhotoReference;
use App\Marketplace\Domain\ValueObject\ShippingOption;
use App\Shared\Domain\ValueObject\Money;
use PHPUnit\Framework\TestCase;

final class ListingTest extends TestCase
{
    public function testCreatesFixedPriceDraftAndLogsEveryPrice(): void
    {
        // Arrange
        [$seller, $item] = $this->collectionItem();

        // Act
        $listing = $this->listing($seller, $item);
        $listing->changePrice(new Money(875, 'EUR'));

        // Assert
        self::assertSame(ListingStatus::Draft, $listing->getStatus());
        self::assertSame(875, $listing->getPrice()->amount);
        self::assertCount(2, $listing->getImages());
        self::assertCount(2, $listing->getShippingMethods());
        self::assertCount(2, $listing->getPriceHistory());
        $firstPrice = $listing->getPriceHistory()->first();
        self::assertNotFalse($firstPrice);
        self::assertNull($firstPrice->getPreviousPrice());
    }

    public function testRejectsAnotherUsersPhysicalCard(): void
    {
        // Arrange
        [, $item] = $this->collectionItem();
        $this->expectException(InvalidListing::class);

        // Act
        $this->listing(new User('other@example.com', 'hash'), $item);

        // Assert
        // The expected domain exception is the observable result.
    }

    public function testRequiresFrontAndBackPhotosAndShipping(): void
    {
        // Arrange
        [$seller, $item] = $this->collectionItem();
        $this->expectException(InvalidListing::class);

        // Act
        new Listing(
            $seller,
            $item,
            new Money(750, 'EUR'),
            [],
            [new ListingPhotoReference(ListingImageSide::Front, 'https://images.example/front.jpg')],
        );

        // Assert
        // The expected domain exception is the observable result.
    }

    /** @return array{User, CollectionItem} */
    private function collectionItem(): array
    {
        $seller = new User('seller@example.com', 'hash');
        $series = new CardSeries('Promos');
        $set = new CardSet($series, 'Promo set', 'promo', 'en', 25, 25, status: CardSetStatus::Active);
        $card = new CatalogCard($set, 'Mudkip', '19/25', 'Promo', 'Collectible');
        $variant = $card->addVariant(VariantCode::Holo);

        return [
            $seller,
            new CollectionItem($seller, $card, $variant, CardLanguage::English, CardCondition::NearMint),
        ];
    }

    private function listing(User $seller, CollectionItem $item): Listing
    {
        return new Listing(
            $seller,
            $item,
            new Money(750, 'EUR'),
            [
                new ShippingOption(ShippingMethodCode::LetterMail, new Money(150, 'EUR')),
                new ShippingOption(ShippingMethodCode::Pickup, new Money(0, 'EUR')),
            ],
            [
                new ListingPhotoReference(ListingImageSide::Front, 'https://images.example/front.jpg'),
                new ListingPhotoReference(ListingImageSide::Back, 'https://images.example/back.jpg'),
            ],
        );
    }
}
