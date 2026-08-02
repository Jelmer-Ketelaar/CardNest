<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Repository;

use App\Catalog\Domain\Entity\CardSet;

interface CardSetRepository
{
    public function list(CardSetSearchCriteria $criteria): CardSetSearchResult;

    public function get(string $id): ?CardSet;

    public function findByCode(string $code): ?CardSet;

    public function save(CardSet $set): void;
}
