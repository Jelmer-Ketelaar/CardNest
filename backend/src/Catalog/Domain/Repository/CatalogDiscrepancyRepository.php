<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Repository;

use App\Catalog\Domain\Entity\CatalogDiscrepancy;

interface CatalogDiscrepancyRepository
{
    public function get(string $id): ?CatalogDiscrepancy;

    public function findByFingerprint(string $fingerprint): ?CatalogDiscrepancy;

    public function search(CatalogDiscrepancySearchCriteria $criteria): CatalogDiscrepancySearchResult;

    public function countBlocking(string $provider, string $scopeKey, string $language, string $region): int;

    public function countBlockedExternal(string $provider, string $scopeKey, string $language, string $region): int;

    public function save(CatalogDiscrepancy $discrepancy): void;
}
