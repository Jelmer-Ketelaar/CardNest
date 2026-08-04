<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Domain\ValueObject;

use App\Catalog\Domain\ValueObject\PokemonName;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PokemonNameTest extends TestCase
{
    public function testItNormalizesUnicodeAndWhitespaceWithoutChangingDisplayDiacritics(): void
    {
        // Arrange
        $name = "  Flabe\u{0301}be\u{0301}   ";

        // Act
        $pokemonName = PokemonName::fromString($name);

        // Assert
        $this->assertSame('Flabébé', $pokemonName->getCanonical());
        $this->assertSame('flabébé', $pokemonName->getNormalized());
        $this->assertSame('flabebe', $pokemonName->toSlug('pokemon-669'));
    }

    public function testItCreatesDistinctSlugsForGenderSymbols(): void
    {
        // Arrange
        $femaleName = PokemonName::fromString('Nidoran♀');
        $maleName = PokemonName::fromString('Nidoran♂');

        // Act
        $femaleSlug = $femaleName->toSlug('pokemon-29');
        $maleSlug = $maleName->toSlug('pokemon-32');

        // Assert
        $this->assertSame('nidoran-female', $femaleSlug);
        $this->assertSame('nidoran-male', $maleSlug);
        $this->assertNotSame($femaleSlug, $maleSlug);
    }

    #[DataProvider('provideInvalidNames')]
    public function testItRejectsInvalidNames(string $name, string $expectedMessage): void
    {
        // Arrange
        $exception = null;

        // Act
        try {
            PokemonName::fromString($name);
        } catch (InvalidArgumentException $caughtException) {
            $exception = $caughtException;
        }

        // Assert
        $this->assertInstanceOf(InvalidArgumentException::class, $exception);
        $this->assertStringContainsString($expectedMessage, $exception->getMessage());
    }

    /** @return iterable<string, array{string, string}> */
    public static function provideInvalidNames(): iterable
    {
        yield 'empty' => ['   ', 'required'];
        yield 'control character' => ["Pika\u{200B}chu", 'control characters'];
        yield 'too long' => [str_repeat('a', 181), 'supported length'];
    }
}
