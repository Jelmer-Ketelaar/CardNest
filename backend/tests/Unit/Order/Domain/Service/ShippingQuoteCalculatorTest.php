<?php

declare(strict_types=1);

namespace App\Tests\Unit\Order\Domain\Service;

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
use App\Marketplace\Domain\Entity\Listing;
use App\Marketplace\Domain\Entity\ListingImageSide;
use App\Marketplace\Domain\Entity\ShippingMethodCode;
use App\Marketplace\Domain\ValueObject\ListingPhotoReference;
use App\Marketplace\Domain\ValueObject\ShippingOption;
use App\Order\Domain\Exception\InvalidShippingSelection;
use App\Order\Domain\Service\ShippingQuoteCalculator;
use App\Shared\Domain\ValueObject\Money;
use PHPUnit\Framework\TestCase;

final class ShippingQuoteCalculatorTest extends TestCase
{
    public function testUsesHighestSharedShippingPriceForSellerGroup(): void
    {
        // Arrange
        $seller = new User('seller@example.com', 'hash');
        $calculator = new ShippingQuoteCalculator();
        $listings = [
            $this->listing($seller, 150, true),
            $this->listing($seller, 250, false),
        ];

        // Act
        $quotes = $calculator->availableFor($listings);

        // Assert
        self::assertCount(1, $quotes);
        self::assertSame(ShippingMethodCode::LetterMail, $quotes[0]->code);
        self::assertSame(250, $quotes[0]->price->amount);
    }

    public function testRejectsMethodNotSupportedByEveryListing(): void
    {
        // Arrange
        $seller = new User('seller@example.com', 'hash');
        $calculator = new ShippingQuoteCalculator();
        $listings = [
            $this->listing($seller, 150, true),
            $this->listing($seller, 250, false),
        ];
        $this->expectException(InvalidShippingSelection::class);

        // Act
        $calculator->require($listings, ShippingMethodCode::Parcel);

        // Assert
        // The expected domain exception is the observable result.
    }

    private function listing(User $seller, int $letterPrice, bool $parcel): Listing
    {
        $series = new CardSeries('Promos '.$letterPrice);
        $set = new CardSet(
            $series,
            'Promo set '.$letterPrice,
            'promo-'.$letterPrice,
            'en',
            25,
            25,
            status: CardSetStatus::Active,
        );
        $card = new CatalogCard($set, 'Mudkip', (string) $letterPrice, 'Promo', 'Collectible');
        $variant = $card->addVariant(VariantCode::Holo);
        $item = new CollectionItem(new Collection($seller), $card, $variant, CardLanguage::English, CardCondition::NearMint);
        $shipping = [new ShippingOption(ShippingMethodCode::LetterMail, new Money($letterPrice, 'EUR'))];
        if ($parcel) {
            $shipping[] = new ShippingOption(ShippingMethodCode::Parcel, new Money(595, 'EUR'));
        }

        return new Listing(
            $seller,
            $item,
            new Money(750, 'EUR'),
            $shipping,
            [
                new ListingPhotoReference(ListingImageSide::Front, 'https://images.example/front.jpg'),
                new ListingPhotoReference(ListingImageSide::Back, 'https://images.example/back.jpg'),
            ],
        );
    }
}
