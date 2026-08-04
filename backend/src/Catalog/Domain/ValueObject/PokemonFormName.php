<?php

declare(strict_types=1);

namespace App\Catalog\Domain\ValueObject;

use InvalidArgumentException;

final readonly class PokemonFormName
{
    private const string CARD_MECHANIC_PATTERN =
        '/(?:^|[^\p{L}\p{N}])(?:ex|gx|v|vmax|vstar|break|radiant|shining)(?=$|[^\p{L}\p{N}])/iu';

    private function __construct(private PokemonName $name)
    {
    }

    public static function fromString(string $name): self
    {
        $pokemonName = PokemonName::fromString($name);
        if (preg_match(self::CARD_MECHANIC_PATTERN, $pokemonName->getNormalized()) === 1) {
            throw new InvalidArgumentException('A card mechanic cannot be registered as a Pokémon form.');
        }

        return new self($pokemonName);
    }

    public function getCanonical(): string
    {
        return $this->name->getCanonical();
    }

    public function getNormalized(): string
    {
        return $this->name->getNormalized();
    }

    public function toSlug(string $fallback): string
    {
        return $this->name->toSlug($fallback);
    }
}
