<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Persistence;

use App\Catalog\Domain\Entity\CardSet;
use App\Catalog\Domain\Repository\CardSetReleaseFilter;
use App\Catalog\Domain\Repository\CardSetRepository;
use App\Catalog\Domain\Repository\CardSetSearchCriteria;
use App\Catalog\Domain\Repository\CardSetSearchResult;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\Uid\Uuid;
use Throwable;

final readonly class DoctrineCardSetRepository implements CardSetRepository
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function list(CardSetSearchCriteria $criteria): CardSetSearchResult
    {
        $today = new DateTimeImmutable('today', new DateTimeZone('Europe/Amsterdam'));
        $total = (int) $this->baseQuery($criteria, $today)
            ->select('COUNT(s.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $query = $this->baseQuery($criteria, $today)
            ->select('s', 'se')
            ->leftJoin('s.series', 'se');

        if ($criteria->release === CardSetReleaseFilter::Upcoming) {
            $query->orderBy('s.releaseDate', 'ASC')->addOrderBy('s.name', 'ASC');
        } else {
            $query->orderBy('s.name', 'ASC');
        }

        /** @var list<CardSet> $items */
        $items = $query
            ->setFirstResult(($criteria->page - 1) * $criteria->perPage)
            ->setMaxResults($criteria->perPage)
            ->getQuery()
            ->getResult();

        return new CardSetSearchResult($items, $total, $criteria->page, $criteria->perPage);
    }

    public function get(string $id): ?CardSet
    {
        $uuid = $this->parseUuid($id);
        if (!$uuid instanceof Uuid) {
            return null;
        }

        return $this->entityManager->getRepository(CardSet::class)->find($uuid);
    }

    public function findByCode(string $code): ?CardSet
    {
        return $this->entityManager->getRepository(CardSet::class)->findOneBy(['code' => $code]);
    }

    public function save(CardSet $set): void
    {
        $this->entityManager->persist($set);
        $this->entityManager->flush();
    }

    private function baseQuery(CardSetSearchCriteria $criteria, DateTimeImmutable $today): QueryBuilder
    {
        $query = $this->entityManager->createQueryBuilder()
            ->from(CardSet::class, 's');

        if ($criteria->name !== null && trim($criteria->name) !== '') {
            $query
                ->andWhere('LOWER(s.name) LIKE :name')
                ->setParameter('name', '%'.mb_strtolower(trim($criteria->name)).'%');
        }

        if ($criteria->language !== null) {
            $query->andWhere('s.language = :language')->setParameter('language', $criteria->language);
        }

        if ($criteria->release === CardSetReleaseFilter::Upcoming) {
            $query->andWhere('s.releaseDate > :today')->setParameter('today', $today);
        } elseif ($criteria->release === CardSetReleaseFilter::Released) {
            $query->andWhere('s.releaseDate <= :today')->setParameter('today', $today);
        }

        return $query;
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
