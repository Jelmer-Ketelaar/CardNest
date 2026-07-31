<?php

declare(strict_types=1);

namespace App\Tests\Unit\Review\Domain\Entity;

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
use App\Marketplace\Domain\Entity\ShippingMethodCode;
use App\Marketplace\Domain\ValueObject\ListingPhotoReference;
use App\Marketplace\Domain\ValueObject\ShippingOption;
use App\Order\Domain\Entity\Order;
use App\Order\Domain\Entity\OrderStatus;
use App\Order\Domain\ValueObject\ShippingAddress;
use App\Payment\Domain\Entity\Payment;
use App\Review\Domain\Entity\Review;
use App\Review\Domain\Exception\InvalidReview;
use App\Shared\Domain\ValueObject\Money;
use PHPUnit\Framework\TestCase;

final class ReviewTest extends TestCase
{
    public function testBuyerCanReviewSellerAfterCompletedOrder(): void
    {
        // Arrange
        [$order, $buyer, $seller] = $this->completedOrder();

        // Act
        $review = new Review($order, $buyer, $seller, 5, 4, 5, 4, 5, '  Goede verkoper.  ');

        // Assert
        self::assertSame($seller, $review->getReviewedUser());
        self::assertSame(5, $review->getOverallScore());
        self::assertSame('Goede verkoper.', $review->getComment());
    }

    public function testSellerCanReviewBuyerAfterCompletedOrder(): void
    {
        // Arrange
        [$order, $buyer, $seller] = $this->completedOrder();

        // Act
        $review = new Review($order, $seller, $buyer, 4, 4, 4, 5, 4);

        // Assert
        self::assertSame($buyer, $review->getReviewedUser());
        self::assertSame($seller, $review->getReviewer());
    }

    public function testReviewBeforeOrderCompletionIsRejected(): void
    {
        // Arrange
        [$order, $buyer, $seller] = $this->order();
        $order->setWorkflowStatus(OrderStatus::Paid->value);
        $order->recordStatusTransition(OrderStatus::PendingPayment, $buyer);
        $this->expectException(InvalidReview::class);

        // Act
        new Review($order, $buyer, $seller, 5, 5, 5, 5, 5);

        // Assert
        // The expected domain exception is the observable result.
    }

    public function testOrderParticipantCannotReviewThemselves(): void
    {
        // Arrange
        [$order, $buyer] = $this->completedOrder();
        $this->expectException(InvalidReview::class);

        // Act
        new Review($order, $buyer, $buyer, 5, 5, 5, 5, 5);

        // Assert
        // The expected domain exception is the observable result.
    }

    public function testEveryScoreMustBeBetweenOneAndFive(): void
    {
        // Arrange
        [$order, $buyer, $seller] = $this->completedOrder();
        $this->expectException(InvalidReview::class);

        // Act
        new Review($order, $buyer, $seller, 6, 5, 5, 5, 5);

        // Assert
        // The expected domain exception is the observable result.
    }

    /** @return array{Order, User, User} */
    private function completedOrder(): array
    {
        [$order, $buyer, $seller] = $this->order();
        $order->setWorkflowStatus(OrderStatus::Paid->value);
        $order->recordStatusTransition(OrderStatus::PendingPayment, $buyer);
        $order->setWorkflowStatus(OrderStatus::Shipped->value);
        $order->recordStatusTransition(OrderStatus::Paid, $seller);
        $order->setWorkflowStatus(OrderStatus::Completed->value);
        $order->recordStatusTransition(OrderStatus::Shipped, $buyer);

        return [$order, $buyer, $seller];
    }

    /** @return array{Order, User, User} */
    private function order(): array
    {
        $buyer = new User('buyer@example.com', 'hash');
        $seller = new User('seller@example.com', 'hash');
        $series = new CardSeries('Promos');
        $set = new CardSet($series, 'Promo set', 'promo', 'en', 25, 25, status: CardSetStatus::Active);
        $card = new CatalogCard($set, 'Mudkip', '19/25', 'Promo', 'Collectible');
        $variant = $card->addVariant(VariantCode::Holo);
        $source = new CollectionItem($seller, $card, $variant, CardLanguage::English, CardCondition::NearMint);
        $listing = new Listing(
            $seller,
            $source,
            new Money(750, 'EUR'),
            [new ShippingOption(ShippingMethodCode::LetterMail, new Money(150, 'EUR'))],
            [
                new ListingPhotoReference(ListingImageSide::Front, 'https://images.example/front.jpg'),
                new ListingPhotoReference(ListingImageSide::Back, 'https://images.example/back.jpg'),
            ],
        );
        $listing->setWorkflowStatus('reserved');
        $source->markForSale();
        $order = new Order(
            $buyer,
            $seller,
            new Payment($buyer, new Money(900, 'EUR'), 'fake'),
            [$listing],
            ShippingMethodCode::LetterMail,
            new Money(150, 'EUR'),
            new ShippingAddress('Sam Collector', 'Dorpsstraat 1', null, '1234 AB', 'Utrecht', 'NL'),
        );

        return [$order, $buyer, $seller];
    }
}
