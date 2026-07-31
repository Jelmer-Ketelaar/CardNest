<?php

declare(strict_types=1);

namespace App\Collection\Domain\Repository;

use App\Catalog\Domain\Entity\CardVariant;
use App\Catalog\Domain\Entity\CatalogCard;
use App\Collection\Domain\Entity\CardCondition;
use App\Collection\Domain\Entity\CardLanguage;
use App\Collection\Domain\Entity\CollectionItem;
use App\Identity\Domain\Entity\User;
use App\Shared\Domain\ValueObject\Money;

interface CollectionItemRepository
{
    public function search(User $owner, CollectionItemSearchCriteria $criteria): CollectionItemSearchResult;

    public function getForOwner(string $id, User $owner): ?CollectionItem;

    /** @param list<CollectionItem> $items */
    public function saveMany(array $items): void;

    public function save(CollectionItem $item): void;

    /** @return array<string, int> */
    public function duplicateCounts(User $owner): array;

    public function countMatching(
        User $owner,
        CatalogCard $card,
        ?CardLanguage $language,
        ?CardVariant $variant,
        ?CardCondition $minimumCondition,
        ?Money $maximumPrice,
    ): int;
}
