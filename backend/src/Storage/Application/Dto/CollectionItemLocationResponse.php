<?php

declare(strict_types=1);

namespace App\Storage\Application\Dto;

use App\Collection\Domain\Entity\CollectionItem;
use App\Storage\Domain\Entity\CollectionItemLocationHistory;

final readonly class CollectionItemLocationResponse
{
    /**
     * @param list<CollectionItemLocationHistory> $history
     * @param array<string, int>                  $itemCounts
     */
    public function __construct(
        private CollectionItem $item,
        private ?CollectionItemLocationHistory $current,
        private array $history = [],
        private array $itemCounts = [],
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'collectionItemId' => $this->item->getId()->toRfc4122(),
            'current' => $this->current instanceof CollectionItemLocationHistory
                ? $this->entry($this->current)
                : null,
            'history' => array_map($this->entry(...), $this->history),
        ];
    }

    /** @return array<string, mixed> */
    private function entry(CollectionItemLocationHistory $history): array
    {
        $location = $history->getStorageLocation();
        $slot = $history->getBinderSlot();

        return [
            'id' => $history->getId()->toRfc4122(),
            'type' => $location !== null ? 'storage_location' : 'binder_slot',
            'location' => $location !== null
                ? (new StorageLocationResponse(
                    $location,
                    $this->itemCounts[$location->getId()->toRfc4122()] ?? 0,
                ))->toArray()
                : null,
            'binderSlot' => $slot !== null ? [
                'id' => $slot->getId()->toRfc4122(),
                'position' => $slot->getPosition(),
                'page' => [
                    'id' => $slot->getPage()->getId()->toRfc4122(),
                    'number' => $slot->getPage()->getPageNumber(),
                    'side' => $slot->getPage()->getSide()->value,
                ],
                'binder' => [
                    'id' => $slot->getPage()->getBinder()->getId()->toRfc4122(),
                    'name' => $slot->getPage()->getBinder()->getName(),
                ],
            ] : null,
            'placedAt' => $history->getPlacedAt()->format(DATE_ATOM),
            'removedAt' => $history->getRemovedAt()?->format(DATE_ATOM),
            'current' => $history->isCurrent(),
        ];
    }
}
