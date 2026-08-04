<?php

declare(strict_types=1);

namespace App\Tests\Integration\Catalog;

use App\Catalog\Domain\Entity\CatalogImportRun;
use App\Catalog\Domain\Repository\CatalogImportRunRepository;
use RuntimeException;

final readonly class FailingCatalogImportRunRepository implements CatalogImportRunRepository
{
    public function __construct(private CatalogImportRunRepository $decorated)
    {
    }

    public function get(string $id): ?CatalogImportRun
    {
        return $this->decorated->get($id);
    }

    public function findCompletedBySourceVersion(
        string $provider,
        string $scopeKey,
        string $sourceVersion,
    ): ?CatalogImportRun {
        return $this->decorated->findCompletedBySourceVersion($provider, $scopeKey, $sourceVersion);
    }

    public function save(CatalogImportRun $run): void
    {
        $this->decorated->save($run);
    }

    public function updatePublicationProgress(string $id, int $setCount, int $cardCount): void
    {
        throw new RuntimeException('Injected publication progress failure.');
    }
}
