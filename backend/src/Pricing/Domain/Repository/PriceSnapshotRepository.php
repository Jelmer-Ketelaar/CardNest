<?php

declare(strict_types=1);

namespace App\Pricing\Domain\Repository;

use App\Catalog\Domain\Entity\CatalogCard;
use App\Pricing\Domain\Entity\PriceSnapshot;
use DateTimeImmutable;

interface PriceSnapshotRepository
{
    public function hasObservationOn(
        CatalogCard $card,
        string $source,
        string $market,
        DateTimeImmutable $observedOn,
    ): bool;

    /** @param list<PriceSnapshot> $snapshots */
    public function replaceDaily(
        CatalogCard $card,
        string $source,
        string $market,
        DateTimeImmutable $observedOn,
        array $snapshots,
    ): void;

    /** @return list<PriceSnapshot> */
    public function history(
        CatalogCard $card,
        string $source,
        string $market,
        DateTimeImmutable $since,
    ): array;
}
