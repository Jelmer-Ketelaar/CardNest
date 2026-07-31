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
use App\Marketplace\Domain\Entity\ShippingMethodCode;
use App\Marketplace\Domain\ValueObject\ListingPhotoReference;
use App\Marketplace\Domain\ValueObject\ShippingOption;
use App\Order\Domain\Entity\Order;
use App\Order\Domain\Entity\OrderItem;
use App\Order\Domain\Entity\OrderStatus;
use App\Order\Domain\Exception\InvalidCollectionImport;
use App\Order\Domain\ValueObject\ShippingAddress;
use App\Payment\Domain\Entity\Payment;
use App\Shared\Domain\ValueObject\Money;
use PHPUnit\Framework\TestCase;

final class OrderItemCollectionImportTest extends TestCase
{
    public function testCompletedPurchaseCanBeLinkedToOneBuyerCollectionItem(): void
    {
        // Arrange
        [$order, $orderItem, $source, $buyer, $seller] = $this->order();
        $this->complete($order, $buyer, $seller);
        $source->completeSale();
        $imported = $this->buyerItem($source, $buyer, $orderItem, $order);

        // Act
        $orderItem->recordCollectionImport($imported, $buyer);

        // Assert
        self::assertSame($imported, $orderItem->getImportedCollectionItem());
        self::assertNotNull($orderItem->getImportedAt());
        self::assertFalse($orderItem->canBeImportedBy($buyer));
    }

    public function testPaidPurchaseCannotBeImportedBeforeReceipt(): void
    {
        // Arrange
        [$order, $orderItem, $source, $buyer] = $this->order();
        $order->setWorkflowStatus(OrderStatus::Paid->value);
        $order->recordStatusTransition(OrderStatus::PendingPayment, $buyer);
        $imported = $this->buyerItem($source, $buyer, $orderItem, $order);
        $this->expectException(InvalidCollectionImport::class);

        // Act
        $orderItem->recordCollectionImport($imported, $buyer);

        // Assert
        // The expected domain exception is the observable result.
    }

    public function testCompletedPurchaseCannotBeImportedTwice(): void
    {
        // Arrange
        [$order, $orderItem, $source, $buyer, $seller] = $this->order();
        $this->complete($order, $buyer, $seller);
        $source->completeSale();
        $orderItem->recordCollectionImport($this->buyerItem($source, $buyer, $orderItem, $order), $buyer);
        $this->expectException(InvalidCollectionImport::class);

        // Act
        $orderItem->recordCollectionImport($this->buyerItem($source, $buyer, $orderItem, $order), $buyer);

        // Assert
        // The expected domain exception is the observable result.
    }

    public function testImportedPurchaseMustKeepTheOrderPrice(): void
    {
        // Arrange
        [$order, $orderItem, $source, $buyer, $seller] = $this->order();
        $this->complete($order, $buyer, $seller);
        $source->completeSale();
        $imported = new CollectionItem(
            $buyer,
            $source->getCatalogCard(),
            $source->getVariant(),
            CardLanguage::French,
            CardCondition::Excellent,
            new Money(700, 'EUR'),
            $order->getPaidAt(),
        );
        $this->expectException(InvalidCollectionImport::class);

        // Act
        $orderItem->recordCollectionImport($imported, $buyer);

        // Assert
        // The expected domain exception is the observable result.
    }

    /** @return array{Order, OrderItem, CollectionItem, User, User} */
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
        $orderItem = $order->getItems()->first();
        self::assertInstanceOf(OrderItem::class, $orderItem);

        return [$order, $orderItem, $source, $buyer, $seller];
    }

    private function complete(Order $order, User $buyer, User $seller): void
    {
        $order->setWorkflowStatus(OrderStatus::Paid->value);
        $order->recordStatusTransition(OrderStatus::PendingPayment, $buyer);
        $order->setWorkflowStatus(OrderStatus::Shipped->value);
        $order->recordStatusTransition(OrderStatus::Paid, $seller);
        $order->setWorkflowStatus(OrderStatus::Completed->value);
        $order->recordStatusTransition(OrderStatus::Shipped, $buyer);
    }

    private function buyerItem(CollectionItem $source, User $buyer, OrderItem $orderItem, Order $order): CollectionItem
    {
        return new CollectionItem(
            $buyer,
            $source->getCatalogCard(),
            $source->getVariant(),
            CardLanguage::French,
            CardCondition::Excellent,
            $orderItem->getPrice(),
            $order->getPaidAt(),
        );
    }
}
