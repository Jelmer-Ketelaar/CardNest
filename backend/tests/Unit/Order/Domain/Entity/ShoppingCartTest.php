<?php

declare(strict_types=1);

namespace App\Tests\Unit\Order\Domain\Entity;

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
use App\Marketplace\Domain\Exception\CannotPurchaseOwnListing;
use App\Marketplace\Domain\ValueObject\ListingPhotoReference;
use App\Marketplace\Domain\ValueObject\ShippingOption;
use App\Order\Domain\Entity\ShoppingCart;
use App\Order\Domain\Exception\InvalidCart;
use App\Shared\Domain\ValueObject\Money;
use PHPUnit\Framework\TestCase;

final class ShoppingCartTest extends TestCase
{
    public function testAddsPhysicalListingAndRetainsOriginalPrice(): void
    {
        // Arrange
        $buyer = new User('buyer@example.com', 'hash');
        $listing = $this->listing(new User('seller@example.com', 'hash'));
        $cart = new ShoppingCart($buyer);

        // Act
        $item = $cart->add($listing);
        $listing->changePrice(new Money(900, 'EUR'));

        // Assert
        self::assertCount(1, $cart->getItems());
        self::assertSame(750, $item->getAddedPrice()->amount);
        self::assertTrue($item->isPriceChanged());
        self::assertSame(900, $item->getListing()->getPrice()->amount);
    }

    public function testRejectsDuplicateListing(): void
    {
        // Arrange
        $cart = new ShoppingCart(new User('buyer@example.com', 'hash'));
        $listing = $this->listing(new User('seller@example.com', 'hash'));
        $cart->add($listing);
        $this->expectException(InvalidCart::class);

        // Act
        $cart->add($listing);

        // Assert
        // The expected domain exception is the observable result.
    }

    public function testRejectsSellersOwnListing(): void
    {
        // Arrange
        $seller = new User('seller@example.com', 'hash');
        $cart = new ShoppingCart($seller);
        $this->expectException(CannotPurchaseOwnListing::class);

        // Act
        $cart->add($this->listing($seller));

        // Assert
        // The expected domain exception is the observable result.
    }

    private function listing(User $seller): Listing
    {
        $series = new CardSeries('Promos');
        $set = new CardSet($series, 'Promo set', 'promo', 'en', 25, 25, status: CardSetStatus::Active);
        $card = new CatalogCard($set, 'Mudkip', '19/25', 'Promo', 'Collectible');
        $variant = $card->addVariant(VariantCode::Holo);
        $ownedItem = new CollectionItem(
            $seller,
            $card,
            $variant,
            CardLanguage::English,
            CardCondition::NearMint,
        );

        return new Listing(
            $seller,
            $ownedItem,
            new Money(750, 'EUR'),
            [new ShippingOption(ShippingMethodCode::LetterMail, new Money(150, 'EUR'))],
            [
                new ListingPhotoReference(ListingImageSide::Front, 'https://images.example/front.jpg'),
                new ListingPhotoReference(ListingImageSide::Back, 'https://images.example/back.jpg'),
            ],
            status: ListingStatus::Active,
        );
    }
}
