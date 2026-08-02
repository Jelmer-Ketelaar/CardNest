<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Import;

use App\Catalog\Application\Import\CatalogImportStore;
use App\Catalog\Domain\Entity\CardSeries;
use App\Catalog\Domain\Entity\CardSet;
use App\Catalog\Domain\Entity\CatalogCard;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineCatalogImportStore implements CatalogImportStore
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function findSeriesBySourceKey(string $sourceKey): ?CardSeries
    {
        /** @var CardSeries|null $series */
        $series = $this->entityManager->getRepository(CardSeries::class)->findOneBy(['sourceKey' => $sourceKey]);

        return $series;
    }

    public function findSeriesByName(string $name): ?CardSeries
    {
        /** @var CardSeries|null $series */
        $series = $this->entityManager->getRepository(CardSeries::class)->findOneBy(['name' => $name]);

        return $series;
    }

    public function findSetByCode(string $code): ?CardSet
    {
        /** @var CardSet|null $set */
        $set = $this->entityManager->getRepository(CardSet::class)->findOneBy(['code' => $code]);

        return $set;
    }

    public function findCardByExternalReference(string $sourceName, string $externalId): ?CatalogCard
    {
        /** @var CatalogCard|null $card */
        $card = $this->entityManager->createQueryBuilder()
            ->select('c', 'r')
            ->from(CatalogCard::class, 'c')
            ->join('c.externalReferences', 'r')
            ->andWhere('r.sourceName = :sourceName')
            ->andWhere('r.externalId = :externalId')
            ->setParameter('sourceName', $sourceName)
            ->setParameter('externalId', $externalId)
            ->getQuery()
            ->getOneOrNullResult();

        return $card;
    }

    public function persist(CardSeries|CardSet|CatalogCard $entity): void
    {
        $this->entityManager->persist($entity);
    }

    public function flush(): void
    {
        $this->entityManager->flush();
    }

    public function clear(): void
    {
        $this->entityManager->clear();
    }
}
