<?php

declare(strict_types=1);

namespace App\Collection\Application;

use App\Collection\Domain\Repository\CollectionItemRepository;
use App\Identity\Domain\Entity\User;
use App\Storage\Domain\Repository\CollectionItemLocationRepository;

final readonly class ArchiveCollectionItem
{
    public function __construct(
        private FindCollectionItems $finder,
        private CollectionItemRepository $collectionItems,
        private CollectionItemLocationRepository $locations,
    ) {
    }

    public function archive(User $owner, string $id): void
    {
        $item = $this->finder->findOwnedItem($owner, $id);
        $item->archive();
        $this->locations->removeCurrent($item);
        $this->collectionItems->save($item);
    }
}
