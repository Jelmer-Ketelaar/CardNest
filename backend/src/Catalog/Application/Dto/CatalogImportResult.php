<?php

declare(strict_types=1);

namespace App\Catalog\Application\Dto;

use App\Catalog\Domain\Entity\CatalogImportRun;
use App\Catalog\Domain\Entity\CatalogImportStatus;

final class CatalogImportResult
{
    private int $createdSets = 0;

    private int $existingSets = 0;

    private int $createdCards = 0;

    private int $syncedCards = 0;

    private ?CatalogImportRun $run = null;

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

    public function merge(self $result): void
    {
        $this->createdSets += $result->createdSets;
        $this->existingSets += $result->existingSets;
        $this->createdCards += $result->createdCards;
        $this->syncedCards += $result->syncedCards;
    }

    public function attachRun(CatalogImportRun $run): void
    {
        $this->run = $run;
    }

    public function getRunId(): ?string
    {
        return $this->run?->getId()->toRfc4122();
    }

    public function getRunStatus(): ?CatalogImportStatus
    {
        return $this->run?->getStatus();
    }

    public function getContentChecksum(): ?string
    {
        return $this->run?->getContentChecksum();
    }
}
