<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Import;

use App\Catalog\Application\Import\CatalogArtistImportStore;
use App\Catalog\Domain\Entity\Artist;
use App\Catalog\Domain\Entity\CardSet;
use App\Catalog\Domain\Entity\CatalogCard;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineCatalogArtistImportStore implements CatalogArtistImportStore
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function catalogLanguages(): array
    {
        /** @var list<array{language: string}> $rows */
        $rows = $this->entityManager->createQueryBuilder()
            ->select('DISTINCT cardSet.language AS language')
            ->from(CardSet::class, 'cardSet')
            ->orderBy('cardSet.language', 'ASC')
            ->getQuery()
            ->getArrayResult();

        return array_column($rows, 'language');
    }

    public function findArtistByNormalizedName(string $normalizedName): ?Artist
    {
        $artist = $this->entityManager->getRepository(Artist::class)->findOneBy([
            'normalizedName' => $normalizedName,
        ]);

        return $artist instanceof Artist ? $artist : null;
    }

    public function findArtistBySlug(string $slug): ?Artist
    {
        $artist = $this->entityManager->getRepository(Artist::class)->findOneBy(['slug' => $slug]);

        return $artist instanceof Artist ? $artist : null;
    }

    public function findCardsByExternalReferences(string $sourceName, array $externalReferences): array
    {
        /** @var list<CatalogCard> $cards */
        $cards = $this->entityManager->createQueryBuilder()
            ->select('card', 'reference', 'artistLink', 'artist')
            ->from(CatalogCard::class, 'card')
            ->join('card.externalReferences', 'reference')
            ->leftJoin('card.artistLinks', 'artistLink')
            ->leftJoin('artistLink.artist', 'artist')
            ->andWhere('reference.sourceName = :sourceName')
            ->andWhere('reference.externalId IN (:externalReferences)')
            ->setParameter('sourceName', $sourceName)
            ->setParameter('externalReferences', $externalReferences)
            ->getQuery()
            ->getResult();

        $cardsByReference = [];
        $referenceLookup = array_fill_keys($externalReferences, true);
        foreach ($cards as $card) {
            foreach ($card->getExternalReferences() as $reference) {
                if (
                    $reference->getSourceName() === $sourceName
                    && isset($referenceLookup[$reference->getExternalId()])
                ) {
                    $cardsByReference[$reference->getExternalId()] = $card;
                }
            }
        }

        return $cardsByReference;
    }

    public function findUnlinkedCardsWithIllustrator(int $limit): array
    {
        /** @var list<CatalogCard> $cards */
        $cards = $this->entityManager->createQueryBuilder()
            ->select('card')
            ->from(CatalogCard::class, 'card')
            ->leftJoin('card.artistLinks', 'artistLink')
            ->where('card.illustrator IS NOT NULL')
            ->andWhere("TRIM(card.illustrator) <> ''")
            ->andWhere('artistLink.id IS NULL')
            ->orderBy('card.id', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return $cards;
    }

    public function persist(Artist|CatalogCard $entity): void
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
