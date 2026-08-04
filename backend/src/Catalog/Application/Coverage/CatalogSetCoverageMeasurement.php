<?php

declare(strict_types=1);

namespace App\Catalog\Application\Coverage;

final readonly class CatalogSetCoverageMeasurement
{
    public function __construct(
        public string $catalogCode,
        public int $cardCount,
        public int $stableOrderCardCount,
        public ?int $officialOrderCardCount,
        public int $imageCardCount,
        public int $artistCardCount,
        public int $pricedCardCount,
    ) {
    }
}
