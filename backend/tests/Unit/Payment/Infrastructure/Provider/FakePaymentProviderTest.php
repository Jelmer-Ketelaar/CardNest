<?php

declare(strict_types=1);

namespace App\Tests\Unit\Payment\Infrastructure\Provider;

use App\Payment\Domain\Provider\RefundInstruction;
use App\Payment\Infrastructure\Provider\FakePaymentProvider;
use App\Shared\Domain\ValueObject\Money;
use PHPUnit\Framework\TestCase;

final class FakePaymentProviderTest extends TestCase
{
    public function testProducesAStableSuccessfulRefundReferenceForTheSameIdempotencyKey(): void
    {
        // Arrange
        $provider = new FakePaymentProvider();
        $instruction = new RefundInstruction('fake_payment', new Money(2_400, 'EUR'), 'refund-order-123', 'Cancelled');

        // Act
        $first = $provider->refund($instruction);
        $second = $provider->refund($instruction);

        // Assert
        self::assertTrue($first->succeeded);
        self::assertSame('fake_refund_refund-order-123', $first->providerReference);
        self::assertSame($first->providerReference, $second->providerReference);
    }

    public function testCanSimulateARefundFailure(): void
    {
        // Arrange
        $provider = new FakePaymentProvider(refundsShouldFail: true);

        // Act
        $result = $provider->refund(
            new RefundInstruction('fake_payment', new Money(2_400, 'EUR'), 'refund-order-123', 'Cancelled'),
        );

        // Assert
        self::assertFalse($result->succeeded);
        self::assertNull($result->providerReference);
        self::assertNotNull($result->failureReason);
    }
}
