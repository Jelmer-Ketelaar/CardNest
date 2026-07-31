<?php

declare(strict_types=1);

namespace App\Collection\Application;

use App\Collection\Application\Dto\CollectionItemCollectionResponse;
use App\Collection\Application\Dto\CollectionItemResponse;
use App\Collection\Application\Dto\ListCollectionItemsRequest;
use App\Collection\Application\Exception\CollectionItemNotFound;
use App\Collection\Domain\Entity\CollectionItem;
use App\Collection\Domain\Entity\CollectionItemStatus;
use App\Collection\Domain\Repository\CollectionItemRepository;
use App\Collection\Domain\Repository\CollectionItemSearchCriteria;
use App\Identity\Domain\Entity\User;

final readonly class FindCollectionItems
{
    public function __construct(private CollectionItemRepository $collectionItems)
    {
    }

    public function list(User $owner, ListCollectionItemsRequest $request): CollectionItemCollectionResponse
    {
        $result = $this->collectionItems->search($owner, new CollectionItemSearchCriteria(
            cardId: $request->cardId,
            setId: $request->setId,
            cardName: $request->cardName,
            cardNumber: $request->cardNumber,
            language: $request->language,
            variant: $request->variant,
            condition: $request->condition,
            status: $request->status,
            page: $request->page,
            perPage: $request->perPage,
        ));

        return new CollectionItemCollectionResponse(
            $result->items,
            $this->collectionItems->duplicateCounts($owner),
            $result->total,
            $result->page,
            $result->perPage,
        );
    }

    public function get(User $owner, string $id): CollectionItemResponse
    {
        $item = $this->findOwnedItem($owner, $id);
        $counts = $this->collectionItems->duplicateCounts($owner);
        $quantity = $item->getStatus() === CollectionItemStatus::InCollection
            ? $counts[$item->duplicateKey()] ?? 1
            : 0;

        return new CollectionItemResponse($item, $quantity);
    }

    public function findOwnedItem(User $owner, string $id): CollectionItem
    {
        $item = $this->collectionItems->getForOwner($id, $owner);
        if (!$item instanceof CollectionItem) {
            throw new CollectionItemNotFound('Collectiekaart niet gevonden.');
        }

        return $item;
    }
}
