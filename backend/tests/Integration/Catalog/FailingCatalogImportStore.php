<?php

declare(strict_types=1);

namespace App\Tests\Integration\Catalog;

use App\Catalog\Application\Import\CatalogImportStore;
use App\Catalog\Domain\Entity\Artist;
use App\Catalog\Domain\Entity\CardSeries;
use App\Catalog\Domain\Entity\CardSet;
use App\Catalog\Domain\Entity\CatalogCard;
use RuntimeException;

final readonly class FailingCatalogImportStore implements CatalogImportStore
{
    public function __construct(
        private CatalogImportStore $decorated,
        private string $failedCardName = 'Mudkip',
    ) {
    }

    public function findSeriesBySourceKey(string $sourceKey): ?CardSeries
    {
        return $this->decorated->findSeriesBySourceKey($sourceKey);
    }

    public function findSeriesByName(string $name): ?CardSeries
    {
        return $this->decorated->findSeriesByName($name);
    }

    public function findSetByCode(string $code): ?CardSet
    {
        return $this->decorated->findSetByCode($code);
    }

    public function findCardByExternalReference(string $sourceName, string $externalId): ?CatalogCard
    {
        return $this->decorated->findCardByExternalReference($sourceName, $externalId);
    }

    public function findArtistByNormalizedName(string $normalizedName): ?Artist
    {
        return $this->decorated->findArtistByNormalizedName($normalizedName);
    }

    public function persist(CardSeries|CardSet|CatalogCard|Artist $entity): void
    {
        if ($entity instanceof CatalogCard && $entity->getName() === $this->failedCardName) {
            throw new RuntimeException('Injected card publication failure.');
        }

        $this->decorated->persist($entity);
    }

    public function flush(): void
    {
        $this->decorated->flush();
    }

    public function clear(): void
    {
        $this->decorated->clear();
    }

    public function runInTransaction(callable $operation): mixed
    {
        return $this->decorated->runInTransaction($operation);
    }
}
