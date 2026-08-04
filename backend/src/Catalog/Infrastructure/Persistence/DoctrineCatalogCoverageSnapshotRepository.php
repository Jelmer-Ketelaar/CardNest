<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Persistence;

use App\Catalog\Domain\Entity\CatalogCoverageSnapshot;
use App\Catalog\Domain\Repository\CatalogCoverageSnapshotRepository;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineCatalogCoverageSnapshotRepository implements CatalogCoverageSnapshotRepository
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function findByManifest(string $importRunId, string $checksum): ?CatalogCoverageSnapshot
    {
        $snapshot = $this->entityManager->getRepository(CatalogCoverageSnapshot::class)->findOneBy([
            'importRun' => $importRunId,
            'manifestChecksum' => $checksum,
        ]);

        return $snapshot instanceof CatalogCoverageSnapshot ? $snapshot : null;
    }

    public function latest(string $provider, string $region, string $language): ?CatalogCoverageSnapshot
    {
        $snapshot = $this->entityManager->getRepository(CatalogCoverageSnapshot::class)->findOneBy(
            [
                'provider' => $provider,
                'region' => $region,
                'language' => $language,
            ],
            ['createdAt' => 'DESC', 'id' => 'DESC'],
        );

        return $snapshot instanceof CatalogCoverageSnapshot ? $snapshot : null;
    }

    public function save(CatalogCoverageSnapshot $snapshot): void
    {
        $this->entityManager->persist($snapshot);
        $this->entityManager->flush();
    }
}
