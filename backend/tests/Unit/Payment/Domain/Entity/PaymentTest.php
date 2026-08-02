<?php

declare(strict_types=1);

namespace App\Tests\Unit\Payment\Domain\Entity;

use App\Identity\Domain\Entity\User;
use App\Order\Domain\Entity\ShoppingCart;
use App\Payment\Domain\Entity\Payment;
use App\Payment\Domain\Entity\PaymentFinalizationStatus;
use App\Payment\Domain\Entity\PaymentStatus;
use App\Payment\Domain\Entity\PaymentTransactionStatus;
use App\Payment\Domain\Exception\InvalidPayment;
use App\Payment\Domain\Provider\PaymentResult;
use App\Shared\Domain\ValueObject\Money;
use PHPUnit\Framework\TestCase;

final class PaymentTest extends TestCase
{
    public function testRecordsSuccessfulChargeWithoutPaymentDetails(): void
    {
        // Arrange
        $payment = new Payment(new User('buyer@example.com', 'hash'), new Money(1200, 'EUR'), 'fake');

        // Act
        $payment->recordResult(PaymentResult::succeeded(new Money(1200, 'EUR'), 'fake_reference'));

        // Assert
        self::assertSame(PaymentStatus::Succeeded, $payment->getStatus());
        self::assertSame(PaymentFinalizationStatus::NotRequired, $payment->getFinalizationStatus());
        self::assertSame('fake_reference', $payment->getProviderReference());
        self::assertNotNull($payment->getProviderResultRecordedAt());
        self::assertCount(1, $payment->getTransactions());
        $transaction = $payment->getTransactions()->first();
        self::assertNotFalse($transaction);
        self::assertSame(PaymentTransactionStatus::Succeeded, $transaction->getStatus());
        self::assertSame(1200, $transaction->getAmount()->amount);
    }

    public function testRecordsFailedChargeWithoutProviderReference(): void
    {
        // Arrange
        $payment = new Payment(new User('buyer@example.com', 'hash'), new Money(1200, 'EUR'), 'fake');

        // Act
        $payment->recordResult(PaymentResult::failed(new Money(1200, 'EUR'), 'Testbetaling geweigerd.'));

        // Assert
        self::assertSame(PaymentStatus::Failed, $payment->getStatus());
        self::assertNull($payment->getProviderReference());
        self::assertCount(1, $payment->getTransactions());
        $transaction = $payment->getTransactions()->first();
        self::assertNotFalse($transaction);
        self::assertSame(PaymentTransactionStatus::Failed, $transaction->getStatus());
        self::assertSame('Testbetaling geweigerd.', $transaction->getFailureReason());
    }

    public function testTracksCheckoutIntentAndFinalizationWithoutPaymentDetails(): void
    {
        // Arrange
        $buyer = new User('checkout-buyer@example.com', 'hash');
        $payment = new Payment(
            buyer: $buyer,
            amount: new Money(1200, 'EUR'),
            providerName: 'fake',
            idempotencyKey: 'checkout-stable-key',
            checkoutCart: new ShoppingCart($buyer),
            requestFingerprint: str_repeat('a', 64),
            cartFingerprint: str_repeat('b', 64),
        );

        // Act
        $payment->recordResult(PaymentResult::succeeded(new Money(1200, 'EUR'), 'fake_reference'));
        $payment->markFinalizationAttempted();
        $payment->markFinalized();

        // Assert
        self::assertSame('checkout-stable-key', $payment->getIdempotencyKey());
        self::assertSame(PaymentFinalizationStatus::Finalized, $payment->getFinalizationStatus());
        self::assertNotNull($payment->getFinalizationAttemptedAt());
        self::assertNotNull($payment->getFinalizedAt());
    }

    public function testRejectsProviderResultForADifferentAmountOrCurrency(): void
    {
        // Arrange
        $payment = new Payment(new User('buyer@example.com', 'hash'), new Money(1200, 'EUR'), 'fake');

        // Assert
        $this->expectException(InvalidPayment::class);

        // Act
        $payment->recordResult(PaymentResult::succeeded(new Money(1201, 'EUR'), 'fake_reference'));
    }
}
