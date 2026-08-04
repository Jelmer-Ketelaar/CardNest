<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Repository;

use App\Catalog\Domain\Entity\CatalogImportRun;

interface CatalogImportRunRepository
{
    public function get(string $id): ?CatalogImportRun;

    public function findCompletedBySourceVersion(
        string $provider,
        string $scopeKey,
        string $sourceVersion,
    ): ?CatalogImportRun;

    public function save(CatalogImportRun $run): void;

    public function updatePublicationProgress(string $id, int $setCount, int $cardCount): void;
}
