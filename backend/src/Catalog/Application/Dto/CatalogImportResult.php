<?php

declare(strict_types=1);

namespace App\Catalog\Application\Dto;

final class CatalogImportResult
{
    private int $createdSets = 0;

    private int $existingSets = 0;

    private int $createdCards = 0;

    private int $syncedCards = 0;

    public function recordCreatedSet(): void
    {
        ++$this->createdSets;
    }

    public function recordExistingSet(): void
    {
        ++$this->existingSets;
    }

    public function recordCreatedCard(): void
    {
        ++$this->createdCards;
    }

    public function recordSyncedCard(): void
    {
        ++$this->syncedCards;
    }

    public function getCreatedSets(): int
    {
        return $this->createdSets;
    }

    public function getExistingSets(): int
    {
        return $this->existingSets;
    }

    public function getCreatedCards(): int
    {
        return $this->createdCards;
    }

    public function getSyncedCards(): int
    {
        return $this->syncedCards;
    }
}
