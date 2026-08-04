<?php

declare(strict_types=1);

namespace App\Catalog\Application\Import;

use App\Catalog\Domain\Entity\Artist;
use App\Catalog\Domain\Entity\CardSeries;
use App\Catalog\Domain\Entity\CardSet;
use App\Catalog\Domain\Entity\CatalogCard;

/**
 * Persistence port optimized for a large catalog import. It deliberately
 * exposes batching without leaking Doctrine into the application use case.
 */
interface CatalogImportStore
{
    public function findSeriesBySourceKey(string $sourceKey): ?CardSeries;

    public function findSeriesByName(string $name): ?CardSeries;

    public function findSetByCode(string $code): ?CardSet;

    public function findCardByExternalReference(string $sourceName, string $externalId): ?CatalogCard;

    public function findArtistByNormalizedName(string $normalizedName): ?Artist;

    public function persist(CardSeries|CardSet|CatalogCard|Artist $entity): void;

    public function flush(): void;

    public function clear(): void;

    /**
     * @template T
     *
     * @param callable(): T $operation
     *
     * @return T
     */
    public function runInTransaction(callable $operation): mixed;
}
