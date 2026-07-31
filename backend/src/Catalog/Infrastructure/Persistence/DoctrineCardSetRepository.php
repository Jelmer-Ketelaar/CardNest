<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Persistence;

use App\Catalog\Domain\Entity\CardSet;
use App\Catalog\Domain\Repository\CardSetRepository;
use App\Catalog\Domain\Repository\CardSetSearchResult;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\Uid\Uuid;
use Throwable;

final readonly class DoctrineCardSetRepository implements CardSetRepository
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function list(int $page, int $perPage): CardSetSearchResult
    {
        $total = (int) $this->baseQuery()
            ->select('COUNT(s.id)')
            ->getQuery()
            ->getSingleScalarResult();

        /** @var list<CardSet> $items */
        $items = $this->baseQuery()
            ->select('s', 'se')
            ->leftJoin('s.series', 'se')
            ->orderBy('s.name', 'ASC')
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage)
            ->getQuery()
            ->getResult();

        return new CardSetSearchResult($items, $total, $page, $perPage);
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

    private function baseQuery(): QueryBuilder
    {
        return $this->entityManager->createQueryBuilder()
            ->from(CardSet::class, 's');
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
