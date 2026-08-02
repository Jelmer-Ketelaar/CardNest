<?php

declare(strict_types=1);

namespace App\Pricing\Infrastructure\Persistence;

use App\Catalog\Domain\Entity\CatalogCard;
use App\Pricing\Domain\Entity\PriceSnapshot;
use App\Pricing\Domain\Repository\PriceSnapshotRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrinePriceSnapshotRepository implements PriceSnapshotRepository
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function hasObservationOn(
        CatalogCard $card,
        string $source,
        string $market,
        DateTimeImmutable $observedOn,
    ): bool {
        $count = $this->entityManager->createQueryBuilder()
            ->select('COUNT(snapshot.id)')
            ->from(PriceSnapshot::class, 'snapshot')
            ->andWhere('snapshot.catalogCard = :card')
            ->andWhere('snapshot.source = :source')
            ->andWhere('snapshot.market = :market')
            ->andWhere('snapshot.observedOn = :observedOn')
            ->setParameter('card', $card)
            ->setParameter('source', $source)
            ->setParameter('market', $market)
            ->setParameter('observedOn', $observedOn)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $count > 0;
    }

    public function replaceDaily(
        CatalogCard $card,
        string $source,
        string $market,
        DateTimeImmutable $observedOn,
        array $snapshots,
    ): void {
        /** @var list<PriceSnapshot> $existing */
        $existing = $this->entityManager->getRepository(PriceSnapshot::class)->findBy([
            'catalogCard' => $card,
            'source' => $source,
            'market' => $market,
            'observedOn' => $observedOn,
        ]);
        $existingByVariant = [];
        foreach ($existing as $snapshot) {
            $existingByVariant[$snapshot->getVariant()->value] = $snapshot;
        }

        foreach ($snapshots as $snapshot) {
            $variant = $snapshot->getVariant()->value;
            $stored = $existingByVariant[$variant] ?? null;
            if ($stored instanceof PriceSnapshot) {
                $stored->synchronizeFrom($snapshot);
                unset($existingByVariant[$variant]);
                continue;
            }

            $this->entityManager->persist($snapshot);
        }

        foreach ($existingByVariant as $obsolete) {
            $this->entityManager->remove($obsolete);
        }

        $this->entityManager->flush();
    }

    public function history(
        CatalogCard $card,
        string $source,
        string $market,
        DateTimeImmutable $since,
    ): array {
        /** @var list<PriceSnapshot> $snapshots */
        $snapshots = $this->entityManager->createQueryBuilder()
            ->select('snapshot')
            ->from(PriceSnapshot::class, 'snapshot')
            ->andWhere('snapshot.catalogCard = :card')
            ->andWhere('snapshot.source = :source')
            ->andWhere('snapshot.market = :market')
            ->andWhere('snapshot.observedOn >= :since')
            ->setParameter('card', $card)
            ->setParameter('source', $source)
            ->setParameter('market', $market)
            ->setParameter('since', $since)
            ->orderBy('snapshot.observedOn', 'DESC')
            ->addOrderBy('snapshot.variant', 'ASC')
            ->getQuery()
            ->getResult();

        return $snapshots;
    }
}
