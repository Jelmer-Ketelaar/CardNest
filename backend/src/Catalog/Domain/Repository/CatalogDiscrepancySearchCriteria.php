<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Repository;

use App\Catalog\Domain\Entity\CatalogDiscrepancyResolutionStatus;
use App\Catalog\Domain\Entity\CatalogDiscrepancySeverity;
use App\Catalog\Domain\Entity\CatalogDiscrepancyType;

final readonly class CatalogDiscrepancySearchCriteria
{
    public function __construct(
        public ?CatalogDiscrepancyResolutionStatus $status,
        public ?CatalogDiscrepancySeverity $severity,
        public ?CatalogDiscrepancyType $type,
        public ?string $provider,
        public ?string $language,
        public ?string $region,
        public int $page,
        public int $perPage,
    ) {
    }
}
