<?php

declare(strict_types=1);

namespace App\Tests\Unit\Order\Domain\ValueObject;

use App\Order\Domain\Exception\InvalidOrder;
use App\Order\Domain\ValueObject\CancellationReason;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CancellationReasonTest extends TestCase
{
    public function testNormalizesWhitespace(): void
    {
        // Arrange

        // Act
        $reason = new CancellationReason('  Seller cannot fulfil the order.  ');

        // Assert
        self::assertSame('Seller cannot fulfil the order.', $reason->value);
    }

    #[DataProvider('invalidReasons')]
    public function testRejectsInvalidReasons(string $value): void
    {
        // Arrange
        $this->expectException(InvalidOrder::class);

        // Act
        new CancellationReason($value);

        // Assert
        // The domain exception is the observable result.
    }

    /** @return iterable<string, array{string}> */
    public static function invalidReasons(): iterable
    {
        yield 'empty' => [''];
        yield 'whitespace' => ['   '];
        yield 'too short' => ['No'];
        yield 'too long' => [str_repeat('a', 501)];
    }
}
