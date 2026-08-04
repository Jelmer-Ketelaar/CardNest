<?php

declare(strict_types=1);

namespace App\Catalog\Application\Coverage;

final readonly class CatalogCoverageMeasurement
{
    /** @param list<CatalogSetCoverageMeasurement> $sets */
    public function __construct(
        public array $sets,
        public int $duplicateSetCount,
        public int $duplicateCardCount,
        public int $orphanCardCount,
        public ?int $pokemonSpeciesCount = null,
        public ?int $pokemonFormCount = null,
        public ?int $pokemonMappedCardCount = null,
    ) {
    }

    public function importedSetCount(): int
    {
        return count($this->sets);
    }

    public function importedCardCount(): int
    {
        return array_sum(array_map(
            static fn (CatalogSetCoverageMeasurement $set): int => $set->cardCount,
            $this->sets,
        ));
    }

    public function stableOrderCardCount(): int
    {
        return array_sum(array_map(
            static fn (CatalogSetCoverageMeasurement $set): int => $set->stableOrderCardCount,
            $this->sets,
        ));
    }

    public function officialOrderCardCount(): ?int
    {
        $count = 0;
        foreach ($this->sets as $set) {
            if ($set->officialOrderCardCount === null) {
                return null;
            }
            $count += $set->officialOrderCardCount;
        }

        return $count;
    }

    public function imageCardCount(): int
    {
        return array_sum(array_map(
            static fn (CatalogSetCoverageMeasurement $set): int => $set->imageCardCount,
            $this->sets,
        ));
    }

    public function artistCardCount(): int
    {
        return array_sum(array_map(
            static fn (CatalogSetCoverageMeasurement $set): int => $set->artistCardCount,
            $this->sets,
        ));
    }

    public function pricedCardCount(): int
    {
        return array_sum(array_map(
            static fn (CatalogSetCoverageMeasurement $set): int => $set->pricedCardCount,
            $this->sets,
        ));
    }
}
