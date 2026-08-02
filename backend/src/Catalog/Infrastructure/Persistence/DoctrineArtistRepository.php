<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Persistence;

use App\Catalog\Domain\Entity\Artist;
use App\Catalog\Domain\Entity\CatalogCardArtist;
use App\Catalog\Domain\Repository\ArtistRepository;
use App\Catalog\Domain\Repository\ArtistSearchResult;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\Uid\Uuid;
use Throwable;

final readonly class DoctrineArtistRepository implements ArtistRepository
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function get(string $id): ?Artist
    {
        try {
            $uuid = Uuid::fromString($id);
        } catch (Throwable) {
            return null;
        }

        $artist = $this->entityManager->getRepository(Artist::class)->find($uuid);

        return $artist instanceof Artist ? $artist : null;
    }

    public function getBySlug(string $slug): ?Artist
    {
        $artist = $this->entityManager->getRepository(Artist::class)->findOneBy(['slug' => $slug]);

        return $artist instanceof Artist ? $artist : null;
    }

    public function findByNormalizedName(string $normalizedName): ?Artist
    {
        $artist = $this->entityManager->getRepository(Artist::class)->findOneBy(['normalizedName' => $normalizedName]);

        return $artist instanceof Artist ? $artist : null;
    }

    public function search(string $query, int $limit): array
    {
        $normalizedQuery = mb_strtolower(trim($query));

        /** @var list<Artist> $artists */
        $artists = $this->entityManager->createQueryBuilder()
            ->select('a')
            ->from(Artist::class, 'a')
            ->where('LOWER(a.canonicalName) LIKE :query')
            ->orderBy('CASE WHEN LOWER(a.canonicalName) LIKE :prefix THEN 0 ELSE 1 END', 'ASC')
            ->addOrderBy('a.canonicalName', 'ASC')
            ->setParameter('query', '%'.$normalizedQuery.'%')
            ->setParameter('prefix', $normalizedQuery.'%')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return $artists;
    }

    public function cardCount(Artist $artist): int
    {
        return (int) $this->entityManager->createQueryBuilder()
            ->select('COUNT(link.id)')
            ->from(CatalogCardArtist::class, 'link')
            ->where('link.artist = :artist')
            ->setParameter('artist', $artist)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function cardCounts(array $artists): array
    {
        if ($artists === []) {
            return [];
        }

        /** @var list<array{id: string, cardCount: string|int}> $rows */
        $rows = $this->entityManager->createQueryBuilder()
            ->select('IDENTITY(link.artist) AS id', 'COUNT(link.id) AS cardCount')
            ->from(CatalogCardArtist::class, 'link')
            ->where('link.artist IN (:artists)')
            ->groupBy('link.artist')
            ->setParameter('artists', $artists)
            ->getQuery()
            ->getArrayResult();

        $counts = [];
        foreach ($rows as $row) {
            $counts[$row['id']] = (int) $row['cardCount'];
        }

        return $counts;
    }

    public function list(
        string $query,
        string $sort,
        int $page,
        int $perPage,
        bool $withCardsOnly,
    ): ArtistSearchResult {
        $countQuery = $this->listQuery($query, $withCardsOnly)
            ->select('COUNT(DISTINCT a.id)');
        $total = (int) $countQuery->getQuery()->getSingleScalarResult();

        $itemsQuery = $this->listQuery($query, $withCardsOnly)
            ->select('a')
            ->addSelect('COUNT(link.id) AS HIDDEN cardCount')
            ->groupBy('a.id');
        if ($sort === 'card_count') {
            $itemsQuery->orderBy('cardCount', 'DESC')->addOrderBy('a.canonicalName', 'ASC');
        } else {
            $itemsQuery->orderBy('a.canonicalName', 'ASC');
        }

        /** @var list<Artist> $items */
        $items = $itemsQuery
            ->addOrderBy('a.id', 'ASC')
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage)
            ->getQuery()
            ->getResult();

        return new ArtistSearchResult($items, $total, $page, $perPage);
    }

    private function listQuery(string $query, bool $withCardsOnly): QueryBuilder
    {
        $queryBuilder = $this->entityManager->createQueryBuilder()
            ->from(Artist::class, 'a')
            ->leftJoin('a.cardLinks', 'link');

        if (trim($query) !== '') {
            $queryBuilder
                ->andWhere('LOWER(a.canonicalName) LIKE :query')
                ->setParameter('query', '%'.mb_strtolower(trim($query)).'%');
        }
        if ($withCardsOnly) {
            $queryBuilder->andWhere('link.id IS NOT NULL');
        }

        return $queryBuilder;
    }
}
