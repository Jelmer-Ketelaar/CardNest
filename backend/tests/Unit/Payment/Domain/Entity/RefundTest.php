<?php

declare(strict_types=1);

namespace App\Tests\Unit\Payment\Domain\Entity;

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
use App\Payment\Domain\Entity\PaymentStatus;
use App\Payment\Domain\Entity\PaymentTransaction;
use App\Payment\Domain\Entity\PaymentTransactionType;
use App\Payment\Domain\Entity\Refund;
use App\Payment\Domain\Entity\RefundStatus;
use App\Payment\Domain\Exception\InvalidPayment;
use App\Payment\Domain\Provider\PaymentResult;
use App\Shared\Domain\ValueObject\Money;
use PHPUnit\Framework\TestCase;

final class RefundTest extends TestCase
{
    public function testRecordsAnImmutableRefundTransactionForTheOrderTotal(): void
    {
        // Arrange
        [$payment, $order, $buyer] = $this->paidOrder();
        $refund = new Refund($payment, $order, $order->getTotal(), $buyer, '  Seller cancelled the sale.  ');

        // Act
        $refund->markSucceeded('fake_refund_order');
        $payment->recordSuccessfulRefund($refund);
        $payment->markFullyRefunded();

        // Assert
        self::assertSame(2_250, $refund->getAmount()->amount);
        self::assertSame('EUR', $refund->getAmount()->currency);
        self::assertSame('Seller cancelled the sale.', $refund->getReason());
        self::assertSame(RefundStatus::Succeeded, $refund->getStatus());
        self::assertSame(PaymentStatus::Refunded, $payment->getStatus());
        self::assertCount(2, $payment->getTransactions());
        $transaction = $payment->getTransactions()->last();
        self::assertInstanceOf(PaymentTransaction::class, $transaction);
        self::assertSame(PaymentTransactionType::Refund, $transaction->getType());
        self::assertSame($order, $transaction->getRelatedOrder());
        self::assertSame($order->getTotal()->amount, $transaction->getAmount()->amount);
    }

    public function testRejectsARefundThatDoesNotMatchTheOrderSnapshot(): void
    {
        // Arrange
        [$payment, $order, $buyer] = $this->paidOrder();
        $this->expectException(InvalidPayment::class);

        // Act
        new Refund($payment, $order, new Money(2_399, 'EUR'), $buyer, 'Seller cancelled the sale.');

        // Assert
        // The immutable order total is the source of truth.
    }

    public function testRejectsARefundRequestedByAnUnrelatedUser(): void
    {
        // Arrange
        [$payment, $order] = $this->paidOrder();
        $outsider = new User('outsider@example.com', 'hash');
        $this->expectException(InvalidPayment::class);

        // Act
        new Refund($payment, $order, $order->getTotal(), $outsider, 'Seller cancelled the sale.');

        // Assert
        // Refund requests are limited to the buyer and seller.
    }

    /** @return array{Payment, Order, User} */
    private function paidOrder(): array
    {
        $buyer = new User('buyer@example.com', 'hash');
        $seller = new User('seller@example.com', 'hash');
        $series = new CardSeries('Promos');
        $set = new CardSet($series, 'Promo set', 'promo', 'en', 1, 1, status: CardSetStatus::Active);
        $card = new CatalogCard($set, 'Mudkip', '1/1', 'Promo', 'Collectible');
        $variant = $card->addVariant(VariantCode::Holo);
        $item = new CollectionItem($seller, $card, $variant, CardLanguage::English, CardCondition::NearMint);
        $listing = new Listing(
            seller: $seller,
            collectionItem: $item,
            price: new Money(2_000, 'EUR'),
            shippingOptions: [new ShippingOption(ShippingMethodCode::LetterMail, new Money(250, 'EUR'))],
            photos: [
                new ListingPhotoReference(ListingImageSide::Front, 'https://images.example/front.jpg'),
                new ListingPhotoReference(ListingImageSide::Back, 'https://images.example/back.jpg'),
            ],
        );
        $listing->setWorkflowStatus('reserved');
        $item->markForSale();
        $payment = new Payment($buyer, new Money(2_250, 'EUR'), 'fake');
        $payment->recordResult(PaymentResult::succeeded('fake_payment'));
        $order = new Order(
            buyer: $buyer,
            seller: $seller,
            payment: $payment,
            listings: [$listing],
            shippingMethod: ShippingMethodCode::LetterMail,
            shippingPrice: new Money(250, 'EUR'),
            shippingAddress: new ShippingAddress('Sam Collector', 'Dorpsstraat 1', null, '1234 AB', 'Utrecht', 'NL'),
        );
        $order->setWorkflowStatus(OrderStatus::Paid->value);
        $order->recordStatusTransition(OrderStatus::PendingPayment, $buyer);

        return [$payment, $order, $buyer];
    }
}
