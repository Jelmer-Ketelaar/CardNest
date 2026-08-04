<?php

declare(strict_types=1);

namespace App\Catalog\Application\Import;

use App\Catalog\Domain\Provider\ImportedCardSet;
use App\Catalog\Domain\Provider\ImportedCatalogCard;

interface CatalogImportStagingStore
{
    public function stageSet(string $runId, ImportedCardSet $set, int $position): void;

    public function stageCard(string $runId, ImportedCatalogCard $card, int $position): void;

    /** @return iterable<ImportedCardSet> */
    public function sets(string $runId): iterable;

    /** @return iterable<ImportedCatalogCard> */
    public function cards(string $runId, string $language, string $setExternalId): iterable;

    public function checksum(string $runId): string;

    /** @return array{sets: int, cards: int} */
    public function counts(string $runId): array;

    public function clear(string $runId): void;
}
