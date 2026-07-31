<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Persistence;

use App\Catalog\Domain\Entity\CatalogCard;
use App\Catalog\Domain\Repository\CatalogCardRepository;
use App\Catalog\Domain\Repository\CatalogCardSearchCriteria;
use App\Catalog\Domain\Repository\CatalogCardSearchResult;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;
use Throwable;

final readonly class DoctrineCatalogCardRepository implements CatalogCardRepository
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function search(CatalogCardSearchCriteria $criteria): CatalogCardSearchResult
    {
        $total = (int) $this->baseQuery($criteria)
            ->select('COUNT(c.id)')
            ->getQuery()
            ->getSingleScalarResult();

        /** @var list<CatalogCard> $items */
        $items = $this->baseQuery($criteria)
            ->select('c', 's', 'se')
            ->leftJoin('s.series', 'se')
            ->orderBy('c.name', 'ASC')
            ->addOrderBy('c.numberSortKey', 'ASC')
            ->setFirstResult(($criteria->page - 1) * $criteria->perPage)
            ->setMaxResults($criteria->perPage)
            ->getQuery()
            ->getResult();

        return new CatalogCardSearchResult($items, $total, $criteria->page, $criteria->perPage);
    }

    public function get(string $id): ?CatalogCard
    {
        $uuid = $this->parseUuid($id);
        if (!$uuid instanceof Uuid) {
            return null;
        }

        return $this->entityManager->getRepository(CatalogCard::class)->find($uuid);
    }

    public function save(CatalogCard $card): void
    {
        $this->entityManager->persist($card);
        $this->entityManager->flush();
    }

    private function baseQuery(CatalogCardSearchCriteria $criteria): QueryBuilder
    {
        $qb = $this->entityManager->createQueryBuilder()
            ->from(CatalogCard::class, 'c')
            ->join('c.set', 's');

        if (null !== $criteria->setId) {
            $qb->andWhere('s.id = :setId')->setParameter('setId', Uuid::fromString($criteria->setId), UuidType::NAME);
        }

        if (null !== $criteria->name && '' !== trim($criteria->name)) {
            $qb->andWhere('LOWER(c.name) LIKE :name')->setParameter('name', '%'.mb_strtolower(trim($criteria->name)).'%');
        }

        if (null !== $criteria->number && '' !== trim($criteria->number)) {
            $qb->andWhere('LOWER(c.number) LIKE :number')->setParameter('number', '%'.mb_strtolower(trim($criteria->number)).'%');
        }

        if (null !== $criteria->rarity && '' !== trim($criteria->rarity)) {
            $qb->andWhere('LOWER(c.rarity) = :rarity')->setParameter('rarity', mb_strtolower(trim($criteria->rarity)));
        }

        return $qb;
    }

    private function parseUuid(string $id): ?Uuid
    {
        try {
            return Uuid::fromString($id);
        } catch (Throwable) {
            return null;
        }
    }
}
