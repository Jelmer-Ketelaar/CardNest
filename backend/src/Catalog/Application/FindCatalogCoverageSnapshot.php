<?php

declare(strict_types=1);

namespace App\Catalog\Application;

use App\Catalog\Application\Dto\CatalogCoverageSnapshotResponse;
use App\Catalog\Domain\Entity\CatalogCoverageSnapshot;
use App\Catalog\Domain\Repository\CatalogCoverageSnapshotRepository;

final readonly class FindCatalogCoverageSnapshot
{
    public function __construct(private CatalogCoverageSnapshotRepository $snapshots)
    {
    }

    /** @return array<string, mixed> */
    public function latest(string $provider, string $region, string $language): array
    {
        $snapshot = $this->snapshots->latest($provider, $region, $language);
        if (!$snapshot instanceof CatalogCoverageSnapshot) {
            return CatalogCoverageSnapshotResponse::notVerified($provider, $region, $language);
        }

        return (new CatalogCoverageSnapshotResponse($snapshot))->toArray();
    }
}
