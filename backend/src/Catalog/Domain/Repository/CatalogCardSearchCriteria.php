<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Repository;

use App\Catalog\Domain\Entity\VariantCode;

final readonly class CatalogCardSearchCriteria
{
    public function __construct(
        public ?string $setId = null,
        public ?string $seriesId = null,
        public ?string $name = null,
        public ?string $number = null,
        public ?string $rarity = null,
        public ?VariantCode $variant = null,
        public int $page = 1,
        public int $perPage = 20,
    ) {
    }
}
