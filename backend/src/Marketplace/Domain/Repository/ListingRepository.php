<?php

declare(strict_types=1);

namespace App\Marketplace\Domain\Repository;

use App\Collection\Domain\Entity\CollectionItem;
use App\Identity\Domain\Entity\User;
use App\Marketplace\Domain\Entity\Listing;

interface ListingRepository
{
    public function searchActive(ListingSearchCriteria $criteria): ListingSearchResult;

    /** @return list<Listing> */
    public function findAllForSeller(User $seller): array;

    public function get(string $id): ?Listing;

    public function getActive(string $id): ?Listing;

    public function getForSeller(string $id, User $seller): ?Listing;

    public function hasBlockingListing(CollectionItem $item, ?Listing $except = null): bool;

    public function save(Listing $listing): void;
}
