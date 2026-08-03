<?php

declare(strict_types=1);

namespace App\Catalog\Application\Import;

use App\Catalog\Domain\Entity\Artist;

final class ArtistResolutionContext
{
    /** @var array<string, Artist> */
    private array $artistsByNormalizedName = [];

    /** @var array<string, string> */
    private array $normalizedNamesBySlug = [];

    public function findArtist(string $normalizedName): ?Artist
    {
        return $this->artistsByNormalizedName[$normalizedName] ?? null;
    }

    public function findNormalizedNameForSlug(string $slug): ?string
    {
        return $this->normalizedNamesBySlug[$slug] ?? null;
    }

    public function remember(Artist $artist): void
    {
        $this->artistsByNormalizedName[$artist->getNormalizedName()] = $artist;
        $this->normalizedNamesBySlug[$artist->getSlug()] = $artist->getNormalizedName();
    }
}
