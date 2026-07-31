<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Domain\ValueObject;

use App\Shared\Domain\ValueObject\Money;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MoneyTest extends TestCase
{
    public function testRepresentsMinorUnitsWithoutFloatingPointValues(): void
    {
        // Arrange
        $amount = 750;

        // Act
        $money = new Money($amount, 'EUR');

        // Assert
        self::assertSame(['amount' => 750, 'currency' => 'EUR'], $money->toArray());
    }

    #[DataProvider('provideInvalidMoney')]
    public function testRejectsInvalidMoney(int $amount, string $currency): void
    {
        // Arrange
        $this->expectException(InvalidArgumentException::class);

        // Act
        new Money($amount, $currency);

        // Assert
        // The expected exception is the observable result.
    }

    /** @return iterable<string, array{int, string}> */
    public static function provideInvalidMoney(): iterable
    {
        yield 'negative amount' => [-1, 'EUR'];
        yield 'lowercase currency' => [100, 'eur'];
        yield 'invalid currency length' => [100, 'EURO'];
    }
}
