<?php

declare(strict_types=1);

namespace App\Catalog\Application\Dto;

use App\Catalog\Domain\Entity\CatalogCard;

final readonly class CatalogCardCollectionResponse
{
    /** @param list<CatalogCard> $items */
    public function __construct(
        private array $items,
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
                static fn (CatalogCard $card): array => (new CatalogCardResponse($card))->toArray(),
                $this->items,
            ),
            'page' => $this->page,
            'perPage' => $this->perPage,
            'total' => $this->total,
            'totalPages' => $this->perPage > 0 ? (int) ceil($this->total / $this->perPage) : 0,
        ];
    }
}
