<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog\Domain\ValueObject;

use App\Catalog\Domain\ValueObject\PokemonFormName;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PokemonFormNameTest extends TestCase
{
    public function testItNormalizesAnOfficialFormNameWithoutChangingDisplayDiacritics(): void
    {
        // Arrange
        $name = "  Flabe\u{0301}be\u{0301}   Eternal   Flower  ";

        // Act
        $formName = PokemonFormName::fromString($name);

        // Assert
        $this->assertSame('Flabébé Eternal Flower', $formName->getCanonical());
        $this->assertSame('flabébé eternal flower', $formName->getNormalized());
        $this->assertSame('flabebe-eternal-flower', $formName->toSlug('form-669'));
    }

    #[DataProvider('provideCardMechanicNames')]
    public function testItRejectsCardMechanicsAsFormNames(string $name): void
    {
        // Arrange
        $exception = null;

        // Act
        try {
            PokemonFormName::fromString($name);
        } catch (InvalidArgumentException $caughtException) {
            $exception = $caughtException;
        }

        // Assert
        $this->assertInstanceOf(InvalidArgumentException::class, $exception);
        $this->assertStringContainsString('card mechanic', $exception->getMessage());
    }

    /** @return iterable<string, array{string}> */
    public static function provideCardMechanicNames(): iterable
    {
        yield 'lowercase ex' => ['Pikachu ex'];
        yield 'uppercase EX' => ['Charizard EX'];
        yield 'GX' => ['Zygarde GX'];
        yield 'V' => ['Pikachu V'];
        yield 'VMAX' => ['Pikachu VMAX'];
        yield 'VSTAR' => ['Pikachu VSTAR'];
        yield 'BREAK' => ['Lugia BREAK'];
        yield 'Radiant' => ['Radiant Charizard'];
        yield 'Shining' => ['Shining Mew'];
    }

    #[DataProvider('provideOfficialFormNames')]
    public function testItAcceptsOfficialFormNamesWithoutMechanicTokens(string $name): void
    {
        // Arrange
        $expectedName = $name;

        // Act
        $formName = PokemonFormName::fromString($name);

        // Assert
        $this->assertSame($expectedName, $formName->getCanonical());
    }

    /** @return iterable<string, array{string}> */
    public static function provideOfficialFormNames(): iterable
    {
        yield 'regional' => ['Alolan Form'];
        yield 'mega' => ['Mega Charizard X'];
        yield 'origin' => ['Origin Forme'];
        yield 'gigantamax' => ['Gigantamax Form'];
    }
}
