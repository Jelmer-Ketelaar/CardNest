<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Persistence;

use App\Catalog\Domain\Entity\Artist;
use App\Catalog\Domain\Entity\CatalogCardArtist;
use App\Catalog\Domain\Repository\ArtistRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;
use Throwable;

final readonly class DoctrineArtistRepository implements ArtistRepository
{
    public function __construct(private EntityManagerInterface $entityManager) {}

    public function get(string $id): ?Artist
    {
        try { $uuid = Uuid::fromString($id); } catch (Throwable) { return null; }
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
}
