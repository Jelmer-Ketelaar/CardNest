<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Persistence;

use App\Catalog\Domain\Entity\CatalogDiscrepancy;
use App\Catalog\Domain\Entity\CatalogDiscrepancyResolutionStatus;
use App\Catalog\Domain\Entity\CatalogDiscrepancySeverity;
use App\Catalog\Domain\Repository\CatalogDiscrepancyRepository;
use App\Catalog\Domain\Repository\CatalogDiscrepancySearchCriteria;
use App\Catalog\Domain\Repository\CatalogDiscrepancySearchResult;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineCatalogDiscrepancyRepository implements CatalogDiscrepancyRepository
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function get(string $id): ?CatalogDiscrepancy
    {
        $discrepancy = $this->entityManager->getRepository(CatalogDiscrepancy::class)->find($id);

        return $discrepancy instanceof CatalogDiscrepancy ? $discrepancy : null;
    }

    public function findByFingerprint(string $fingerprint): ?CatalogDiscrepancy
    {
        $discrepancy = $this->entityManager->getRepository(CatalogDiscrepancy::class)->findOneBy([
            'fingerprint' => $fingerprint,
        ]);

        return $discrepancy instanceof CatalogDiscrepancy ? $discrepancy : null;
    }

    public function search(CatalogDiscrepancySearchCriteria $criteria): CatalogDiscrepancySearchResult
    {
        $query = $this->entityManager->createQueryBuilder()
            ->select('discrepancy')
            ->from(CatalogDiscrepancy::class, 'discrepancy');
        $count = $this->entityManager->createQueryBuilder()
            ->select('COUNT(discrepancy.id)')
            ->from(CatalogDiscrepancy::class, 'discrepancy');

        foreach ([$query, $count] as $builder) {
            if ($criteria->status instanceof CatalogDiscrepancyResolutionStatus) {
                $builder->andWhere('discrepancy.resolutionStatus = :status')
                    ->setParameter('status', $criteria->status);
            }
            if ($criteria->severity instanceof CatalogDiscrepancySeverity) {
                $builder->andWhere('discrepancy.severity = :severity')
                    ->setParameter('severity', $criteria->severity);
            }
            if ($criteria->type !== null) {
                $builder->andWhere('discrepancy.discrepancyType = :type')
                    ->setParameter('type', $criteria->type);
            }
            if ($criteria->provider !== null) {
                $builder->andWhere('discrepancy.provider = :provider')
                    ->setParameter('provider', $criteria->provider);
            }
            if ($criteria->language !== null) {
                $builder->andWhere('discrepancy.language = :language')
                    ->setParameter('language', $criteria->language);
            }
            if ($criteria->region !== null) {
                $builder->andWhere('discrepancy.region = :region')
                    ->setParameter('region', $criteria->region);
            }
        }

        /** @var list<CatalogDiscrepancy> $items */
        $items = $query
            ->addSelect(<<<'DQL'
                CASE discrepancy.severity
                    WHEN 'critical' THEN 0
                    WHEN 'high' THEN 1
                    WHEN 'medium' THEN 2
                    WHEN 'low' THEN 3
                    ELSE 4
                END AS HIDDEN severity_rank
                DQL)
            ->orderBy('severity_rank', 'ASC')
            ->addOrderBy('discrepancy.lastSeenAt', 'DESC')
            ->addOrderBy('discrepancy.id', 'ASC')
            ->setFirstResult(($criteria->page - 1) * $criteria->perPage)
            ->setMaxResults($criteria->perPage)
            ->getQuery()
            ->getResult();

        return new CatalogDiscrepancySearchResult($items, (int) $count->getQuery()->getSingleScalarResult());
    }

    public function countBlocking(string $provider, string $scopeKey, string $language, string $region): int
    {
        return $this->countScopedByStatuses($provider, $scopeKey, $language, $region, [
            CatalogDiscrepancyResolutionStatus::Open,
            CatalogDiscrepancyResolutionStatus::BlockedExternal,
        ]);
    }

    public function countBlockedExternal(string $provider, string $scopeKey, string $language, string $region): int
    {
        return $this->countScopedByStatuses($provider, $scopeKey, $language, $region, [
            CatalogDiscrepancyResolutionStatus::BlockedExternal,
        ]);
    }

    public function save(CatalogDiscrepancy $discrepancy): void
    {
        $this->entityManager->persist($discrepancy);
        $this->entityManager->flush();
    }

    /** @param list<CatalogDiscrepancyResolutionStatus> $statuses */
    private function countScopedByStatuses(
        string $provider,
        string $scopeKey,
        string $language,
        string $region,
        array $statuses,
    ): int {
        return (int) $this->entityManager->createQueryBuilder()
            ->select('COUNT(discrepancy.id)')
            ->from(CatalogDiscrepancy::class, 'discrepancy')
            ->andWhere('discrepancy.provider = :provider')
            ->andWhere('discrepancy.scopeKey = :scopeKey')
            ->andWhere('discrepancy.language = :language')
            ->andWhere('(discrepancy.region IS NULL OR discrepancy.region = :region)')
            ->andWhere('discrepancy.severity = :severity')
            ->andWhere('discrepancy.resolutionStatus IN (:statuses)')
            ->setParameter('provider', $provider)
            ->setParameter('scopeKey', $scopeKey)
            ->setParameter('language', $language)
            ->setParameter('region', $region)
            ->setParameter('severity', CatalogDiscrepancySeverity::Critical)
            ->setParameter('statuses', $statuses)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
