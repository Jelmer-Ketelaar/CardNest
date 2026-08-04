<?php

declare(strict_types=1);

namespace App\Catalog\Domain\ValueObject;

use InvalidArgumentException;
use Normalizer;
use Symfony\Component\String\Slugger\AsciiSlugger;

final readonly class PokemonName
{
    private const int MAX_LENGTH = 180;

    private function __construct(
        private string $canonical,
        private string $normalized,
    ) {
    }

    public static function fromString(string $name): self
    {
        $unicodeName = Normalizer::normalize($name, Normalizer::FORM_C);
        if (!is_string($unicodeName)) {
            throw new InvalidArgumentException('A Pokémon name must contain valid Unicode.');
        }

        $canonical = preg_replace('/\s+/u', ' ', trim($unicodeName));
        if (!is_string($canonical) || $canonical === '') {
            throw new InvalidArgumentException('A Pokémon canonical name is required.');
        }
        if (preg_match('/[\p{Cc}\p{Cf}]/u', $canonical) === 1) {
            throw new InvalidArgumentException('A Pokémon name cannot contain control characters.');
        }
        if (mb_strlen($canonical) > self::MAX_LENGTH) {
            throw new InvalidArgumentException('A Pokémon name exceeds the supported length.');
        }

        return new self($canonical, mb_strtolower($canonical));
    }

    public function getCanonical(): string
    {
        return $this->canonical;
    }

    public function getNormalized(): string
    {
        return $this->normalized;
    }

    public function toSlug(string $fallback): string
    {
        $slugSource = str_replace(['♀', '♂'], [' female', ' male'], $this->canonical);
        $slug = (new AsciiSlugger())->slug($slugSource)->lower()->toString();
        if ($slug !== '') {
            return $slug;
        }

        $fallbackSlug = (new AsciiSlugger())->slug($fallback)->lower()->toString();
        if ($fallbackSlug === '') {
            throw new InvalidArgumentException('A Pokémon name requires a usable slug fallback.');
        }

        return $fallbackSlug;
    }
}
