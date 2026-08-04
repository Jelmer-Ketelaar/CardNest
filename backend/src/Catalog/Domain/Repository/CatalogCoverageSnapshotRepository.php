<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Repository;

use App\Catalog\Domain\Entity\CatalogCoverageSnapshot;

interface CatalogCoverageSnapshotRepository
{
    public function findByManifest(string $importRunId, string $checksum): ?CatalogCoverageSnapshot;

    public function latest(string $provider, string $region, string $language): ?CatalogCoverageSnapshot;

    public function save(CatalogCoverageSnapshot $snapshot): void;
}
