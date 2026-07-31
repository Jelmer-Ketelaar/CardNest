<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Repository;

use App\Catalog\Domain\Entity\CatalogCard;

interface CatalogCardRepository
{
    public function search(CatalogCardSearchCriteria $criteria): CatalogCardSearchResult;

    public function get(string $id): ?CatalogCard;

    public function save(CatalogCard $card): void;
}
