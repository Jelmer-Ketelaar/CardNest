<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Repository;

use App\Catalog\Domain\Entity\Artist;

interface ArtistRepository
{
    public function get(string $id): ?Artist;

    public function getBySlug(string $slug): ?Artist;

    public function findByNormalizedName(string $normalizedName): ?Artist;

    /** @return list<Artist> */
    public function search(string $query, int $limit): array;

    public function cardCount(Artist $artist): int;

    /**
     * @param list<Artist> $artists
     *
     * @return array<string, int>
     */
    public function cardCounts(array $artists): array;

    public function list(
        string $query,
        string $sort,
        int $page,
        int $perPage,
        bool $withCardsOnly,
    ): ArtistSearchResult;
}
