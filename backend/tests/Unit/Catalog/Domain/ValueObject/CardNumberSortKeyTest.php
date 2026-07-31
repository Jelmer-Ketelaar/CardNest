<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Domain\ValueObject;

use App\Catalog\Domain\ValueObject\CardNumberSortKey;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CardNumberSortKeyTest extends TestCase
{
    #[DataProvider('provideCardNumbers')]
    public function testGeneratesSortableNormalizedKey(string $number, string $expected): void
    {
        // Arrange
        $input = $number;

        // Act
        $result = CardNumberSortKey::generate($input);

        // Assert
        self::assertSame($expected, $result);
    }

    /** @return iterable<string, array{string, string}> */
    public static function provideCardNumbers(): iterable
    {
        yield 'set fraction' => ['19/25', '000019/000025'];
        yield 'prefixed number' => ['SWSH100', 'swsh000100'];
        yield 'trimmed mixed segments' => [' TG01/TG30 ', 'tg000001/tg000030'];
    }
}
