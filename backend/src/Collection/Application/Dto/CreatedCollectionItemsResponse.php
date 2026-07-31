<?php

declare(strict_types=1);

namespace App\Collection\Application\Dto;

use App\Collection\Domain\Entity\CollectionItem;

final readonly class CreatedCollectionItemsResponse
{
    /**
     * @param list<CollectionItem> $items
     * @param array<string, int>   $duplicateCounts
     */
    public function __construct(
        private array $items,
        private array $duplicateCounts,
    ) {
    }

    /** @return array{items: list<array<string, mixed>>, created: int} */
    public function toArray(): array
    {
        return [
            'items' => array_map(
                fn (CollectionItem $item): array => (new CollectionItemResponse(
                    $item,
                    $this->duplicateCounts[$item->duplicateKey()] ?? 1,
                ))->toArray(),
                $this->items,
            ),
            'created' => count($this->items),
        ];
    }
}
