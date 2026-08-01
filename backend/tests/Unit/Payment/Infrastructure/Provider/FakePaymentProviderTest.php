<?php

declare(strict_types=1);

namespace App\Tests\Unit\Payment\Infrastructure\Provider;

use App\Payment\Domain\Provider\PaymentInstruction;
use App\Payment\Infrastructure\Provider\FailingFakePaymentProvider;
use App\Payment\Infrastructure\Provider\FakePaymentProvider;
use App\Shared\Domain\ValueObject\Money;
use PHPUnit\Framework\TestCase;

final class FakePaymentProviderTest extends TestCase
{
    public function testSuccessfulProviderReturnsDeterministicReferenceForIdempotencyKey(): void
    {
        // Arrange
        $provider = new FakePaymentProvider();
        $instruction = new PaymentInstruction('payment-id', 'buyer-id', new Money(2400, 'EUR'), 'checkout-key');

        // Act
        $firstResult = $provider->charge($instruction);
        $secondResult = $provider->charge($instruction);

        // Assert
        self::assertTrue($firstResult->succeeded);
        self::assertSame(2400, $firstResult->amount->amount);
        self::assertSame('EUR', $firstResult->amount->currency);
        self::assertNotNull($firstResult->providerReference);
        self::assertStringStartsWith('fake_', $firstResult->providerReference);
        self::assertSame($firstResult->providerReference, $secondResult->providerReference);
        self::assertNull($firstResult->failureReason);
    }

    public function testFailingProviderReturnsTheRequestedAmountWithoutReference(): void
    {
        // Arrange
        $provider = new FailingFakePaymentProvider();
        $instruction = new PaymentInstruction('payment-id', 'buyer-id', new Money(2400, 'EUR'), 'checkout-key');

        // Act
        $result = $provider->charge($instruction);

        // Assert
        self::assertFalse($result->succeeded);
        self::assertSame(2400, $result->amount->amount);
        self::assertSame('EUR', $result->amount->currency);
        self::assertNull($result->providerReference);
        self::assertSame('De testbetaling is afgewezen.', $result->failureReason);
    }
}
