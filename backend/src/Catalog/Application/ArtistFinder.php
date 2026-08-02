<?php

declare(strict_types=1);

namespace App\Catalog\Application;

use App\Catalog\Domain\Entity\Artist;
use App\Catalog\Domain\Repository\ArtistRepository;

final readonly class ArtistFinder
{
    public function __construct(private ArtistRepository $artists) {}

    /** @return list<array<string, mixed>> */
    public function suggestions(string $query, int $limit): array
    {
        if (mb_strlen(trim($query)) < 2) {
            return [];
        }

        return array_map(fn (Artist $artist): array => $this->summary($artist), $this->artists->search($query, $limit));
    }

    /** @return array<string, mixed>|null */
    public function get(string $id): ?array
    {
        $artist = $this->artists->get($id);

        return $artist instanceof Artist ? $this->detail($artist) : null;
    }

    /** @return array<string, mixed>|null */
    public function getBySlug(string $slug): ?array
    {
        $artist = $this->artists->getBySlug($slug);

        return $artist instanceof Artist ? $this->detail($artist) : null;
    }

    /** @return array<string, mixed> */
    private function summary(Artist $artist): array
    {
        return [
            'id' => $artist->getId()->toRfc4122(),
            'name' => $artist->getCanonicalName(),
            'slug' => $artist->getSlug(),
            'cardCount' => $this->artists->cardCount($artist),
        ];
    }

    /** @return array<string, mixed> */
    private function detail(Artist $artist): array
    {
        return [
            ...$this->summary($artist),
            'biography' => $artist->getBiography(),
            'websiteUrl' => $artist->getWebsiteUrl(),
            'profileImage' => $artist->getProfileImageUrl(),
        ];
    }
}
