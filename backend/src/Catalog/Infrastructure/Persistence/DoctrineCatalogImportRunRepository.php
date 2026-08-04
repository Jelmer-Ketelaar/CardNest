<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Persistence;

use App\Catalog\Domain\Entity\CatalogImportRun;
use App\Catalog\Domain\Entity\CatalogImportStatus;
use App\Catalog\Domain\Repository\CatalogImportRunRepository;
use DateTimeImmutable;
use Doctrine\DBAL\ParameterType;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;

final readonly class DoctrineCatalogImportRunRepository implements CatalogImportRunRepository
{
    private const array COMPLETED_STATUSES = [
        CatalogImportStatus::Completed->value,
        CatalogImportStatus::CompletedWithWarnings->value,
    ];

    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function get(string $id): ?CatalogImportRun
    {
        $run = $this->entityManager->getRepository(CatalogImportRun::class)->find($id);

        return $run instanceof CatalogImportRun ? $run : null;
    }

    public function findCompletedBySourceVersion(
        string $provider,
        string $scopeKey,
        string $sourceVersion,
    ): ?CatalogImportRun {
        /** @var CatalogImportRun|null $run */
        $run = $this->entityManager->createQueryBuilder()
            ->select('run')
            ->from(CatalogImportRun::class, 'run')
            ->andWhere('run.provider = :provider')
            ->andWhere('run.scopeKey = :scopeKey')
            ->andWhere('run.sourceVersion = :sourceVersion')
            ->andWhere('run.status IN (:statuses)')
            ->setParameter('provider', $provider)
            ->setParameter('scopeKey', $scopeKey)
            ->setParameter('sourceVersion', $sourceVersion)
            ->setParameter('statuses', self::COMPLETED_STATUSES)
            ->orderBy('run.completedAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $run;
    }

    public function save(CatalogImportRun $run): void
    {
        $this->entityManager->persist($run);
        $this->entityManager->flush();
    }

    public function updatePublicationProgress(string $id, int $setCount, int $cardCount): void
    {
        $affectedRows = $this->entityManager->getConnection()->executeStatement(
            <<<'SQL'
                UPDATE catalog_import_runs
                SET published_set_count = :set_count,
                    published_card_count = :card_count,
                    updated_at = :updated_at
                WHERE id = :id
                  AND status = :status
                SQL,
            [
                'id' => $id,
                'set_count' => $setCount,
                'card_count' => $cardCount,
                'status' => CatalogImportStatus::Publishing->value,
                'updated_at' => new DateTimeImmutable(),
            ],
            [
                'id' => ParameterType::STRING,
                'set_count' => ParameterType::INTEGER,
                'card_count' => ParameterType::INTEGER,
                'status' => ParameterType::STRING,
                'updated_at' => 'datetime_immutable',
            ],
        );
        if ($affectedRows !== 1) {
            throw new RuntimeException('Catalog import publication progress could not be recorded.');
        }
    }
}
