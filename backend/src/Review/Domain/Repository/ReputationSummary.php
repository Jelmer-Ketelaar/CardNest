<?php

declare(strict_types=1);

namespace App\Review\Domain\Repository;

final readonly class ReputationSummary
{
    public function __construct(
        public int $reviewCount,
        public ?float $averageOverall,
        public ?float $averageDescriptionAccuracy,
        public ?float $averagePackaging,
        public ?float $averageCommunication,
        public ?float $averageShippingSpeed,
    ) {
    }
}
