<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shipping\Domain\Entity;

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
use App\Shared\Domain\ValueObject\Money;
use App\Shipping\Domain\Entity\Shipment;
use App\Shipping\Domain\Entity\ShipmentStatus;
use App\Shipping\Domain\Exception\InvalidShipment;
use PHPUnit\Framework\TestCase;

final class ShipmentTest extends TestCase
{
    public function testTracksShippedAndDeliveredOrderLifecycle(): void
    {
        // Arrange
        [$order, $buyer, $seller] = $this->order();
        $order->setWorkflowStatus(OrderStatus::Paid->value);
        $order->recordStatusTransition(OrderStatus::PendingPayment, $buyer);
        $order->setWorkflowStatus(OrderStatus::Shipped->value);
        $order->recordStatusTransition(OrderStatus::Paid, $seller);
        $shipment = Shipment::forOrder($order);

        // Act
        $order->setWorkflowStatus(OrderStatus::Completed->value);
        $order->recordStatusTransition(OrderStatus::Shipped, $buyer);
        $shipment->confirmDelivered();

        // Assert
        self::assertSame(ShipmentStatus::Delivered, $shipment->getStatus());
        self::assertNotNull($shipment->getDeliveredAt());
        self::assertSame(
            ['pending_payment', 'paid', 'shipped', 'completed'],
            array_map(static fn ($history): string => $history->getToStatus()->value, $order->getStatusHistory()->toArray()),
        );
    }

    public function testCannotShipOrderBeforeShippedTransition(): void
    {
        // Arrange
        [$order, $buyer] = $this->order();
        $order->setWorkflowStatus(OrderStatus::Paid->value);
        $order->recordStatusTransition(OrderStatus::PendingPayment, $buyer);
        $this->expectException(InvalidShipment::class);

        // Act
        Shipment::forOrder($order);

        // Assert
        // The expected domain exception is the observable result.
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
        $item = new CollectionItem($seller, $card, $variant, CardLanguage::English, CardCondition::NearMint);
        $listing = new Listing(
            $seller,
            $item,
            new Money(750, 'EUR'),
            [new ShippingOption(ShippingMethodCode::LetterMail, new Money(150, 'EUR'))],
            [
                new ListingPhotoReference(ListingImageSide::Front, 'https://images.example/front.jpg'),
                new ListingPhotoReference(ListingImageSide::Back, 'https://images.example/back.jpg'),
            ],
        );
        $listing->setWorkflowStatus('reserved');
        $item->markForSale();

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
