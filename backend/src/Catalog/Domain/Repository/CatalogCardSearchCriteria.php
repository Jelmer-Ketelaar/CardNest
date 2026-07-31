<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Repository;

final readonly class CatalogCardSearchCriteria
{
    public function __construct(
        public ?string $setId = null,
        public ?string $name = null,
        public ?string $number = null,
        public ?string $rarity = null,
        public int $page = 1,
        public int $perPage = 20,
    ) {
    }
}
