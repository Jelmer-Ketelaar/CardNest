<?php

declare(strict_types=1);

namespace App\Catalog\Application\Import;

use App\Catalog\Domain\Entity\Artist;
use App\Catalog\Domain\Entity\CatalogCard;

interface CatalogArtistImportStore
{
    /** @return list<string> */
    public function catalogLanguages(): array;

    public function findArtistByNormalizedName(string $normalizedName): ?Artist;

    public function findArtistBySlug(string $slug): ?Artist;

    /**
     * @param non-empty-list<string> $externalReferences
     *
     * @return array<string, CatalogCard>
     */
    public function findCardsByExternalReferences(string $sourceName, array $externalReferences): array;

    /** @return list<CatalogCard> */
    public function findUnlinkedCardsWithIllustrator(int $limit): array;

    public function persist(Artist|CatalogCard $entity): void;

    public function flush(): void;

    public function clear(): void;
}
