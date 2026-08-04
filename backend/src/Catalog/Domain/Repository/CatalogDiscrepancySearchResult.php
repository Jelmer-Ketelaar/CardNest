<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Repository;

use App\Catalog\Domain\Entity\CatalogDiscrepancy;

final readonly class CatalogDiscrepancySearchResult
{
    /** @param list<CatalogDiscrepancy> $items */
    public function __construct(
        public array $items,
        public int $total,
    ) {
    }
}
