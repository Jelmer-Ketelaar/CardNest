<?php

declare(strict_types=1);

namespace App\Tests\Unit\Payment\Domain\Entity;

use App\Identity\Domain\Entity\User;
use App\Payment\Domain\Entity\Payment;
use App\Payment\Domain\Entity\PaymentStatus;
use App\Payment\Domain\Entity\PaymentTransactionStatus;
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
        $payment->recordResult(PaymentResult::succeeded('fake_reference'));

        // Assert
        self::assertSame(PaymentStatus::Succeeded, $payment->getStatus());
        self::assertSame('fake_reference', $payment->getProviderReference());
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
        $payment->recordResult(PaymentResult::failed('Testbetaling geweigerd.'));

        // Assert
        self::assertSame(PaymentStatus::Failed, $payment->getStatus());
        self::assertNull($payment->getProviderReference());
        self::assertCount(1, $payment->getTransactions());
        $transaction = $payment->getTransactions()->first();
        self::assertNotFalse($transaction);
        self::assertSame(PaymentTransactionStatus::Failed, $transaction->getStatus());
        self::assertSame('Testbetaling geweigerd.', $transaction->getFailureReason());
    }
}
