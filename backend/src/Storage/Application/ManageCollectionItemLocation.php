<?php

declare(strict_types=1);

namespace App\Storage\Application;

use App\Collection\Application\FindCollectionItems;
use App\Collection\Domain\Entity\CollectionItemStatus;
use App\Collection\Domain\Exception\ArchivedCollectionItemCannotBeChanged;
use App\Identity\Domain\Entity\User;
use App\Storage\Application\Dto\CollectionItemLocationResponse;
use App\Storage\Application\Dto\PlaceCollectionItemRequest;
use App\Storage\Domain\Repository\CollectionItemLocationRepository;

final readonly class ManageCollectionItemLocation
{
    public function __construct(
        private FindCollectionItems $collectionItems,
        private StorageLocationResolver $locations,
        private CollectionItemLocationRepository $placements,
    ) {
    }

    public function place(User $owner, string $itemId, PlaceCollectionItemRequest $request): CollectionItemLocationResponse
    {
        $item = $this->collectionItems->findOwnedItem($owner, $itemId);
        $this->assertMutable($item->getStatus());
        $current = $this->placements->place($item, $this->locations->owned($request->locationId, $owner));

        return new CollectionItemLocationResponse(
            $item,
            $current,
            itemCounts: $this->placements->currentItemCounts($owner),
        );
    }

    public function remove(User $owner, string $itemId): void
    {
        $item = $this->collectionItems->findOwnedItem($owner, $itemId);
        $this->assertMutable($item->getStatus());
        $this->placements->removeCurrent($item);
    }

    public function get(User $owner, string $itemId, bool $includeHistory): CollectionItemLocationResponse
    {
        $item = $this->collectionItems->findOwnedItem($owner, $itemId);

        return new CollectionItemLocationResponse(
            $item,
            $this->placements->currentForItem($item),
            $includeHistory ? $this->placements->historyForItem($item) : [],
            $this->placements->currentItemCounts($owner),
        );
    }

    private function assertMutable(CollectionItemStatus $status): void
    {
        if ($status === CollectionItemStatus::Archived) {
            throw new ArchivedCollectionItemCannotBeChanged('Een gearchiveerde collectiekaart kan niet worden verplaatst.');
        }
    }
}
