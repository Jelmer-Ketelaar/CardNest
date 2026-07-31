<?php

declare(strict_types=1);

namespace App\Collection\Application\Dto;

use App\Collection\Domain\Entity\CollectionItem;

final readonly class CollectionItemCollectionResponse
{
    /**
     * @param list<CollectionItem> $items
     * @param array<string, int>   $duplicateCounts
     */
    public function __construct(
        private array $items,
        private array $duplicateCounts,
        private int $total,
        private int $page,
        private int $perPage,
    ) {
    }

    /** @return array<string, mixed> */
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
            'page' => $this->page,
            'perPage' => $this->perPage,
            'total' => $this->total,
            'totalPages' => (int) ceil($this->total / $this->perPage),
        ];
    }
}
