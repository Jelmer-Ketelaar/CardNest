<?php

declare(strict_types=1);

namespace App\Catalog\Application\Dto;

use App\Catalog\Domain\Entity\CatalogDiscrepancy;
use App\Catalog\Domain\Repository\CatalogDiscrepancySearchResult;

final readonly class CatalogDiscrepancyCollectionResponse
{
    public function __construct(
        private CatalogDiscrepancySearchResult $result,
        private int $page,
        private int $perPage,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'items' => array_map(
                static fn (CatalogDiscrepancy $discrepancy): array => (new CatalogDiscrepancyResponse($discrepancy))->toArray(),
                $this->result->items,
            ),
            'page' => $this->page,
            'perPage' => $this->perPage,
            'total' => $this->result->total,
        ];
    }
}
