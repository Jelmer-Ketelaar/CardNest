<?php

declare(strict_types=1);

namespace App\Catalog\Application\Dto;

use App\Catalog\Domain\Entity\CardSet;

final readonly class CardSetCollectionResponse
{
    /** @param list<CardSet> $items */
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
                static fn (CardSet $set): array => (new CardSetResponse($set))->toArray(),
                $this->items,
            ),
            'page' => $this->page,
            'perPage' => $this->perPage,
            'total' => $this->total,
            'totalPages' => $this->perPage > 0 ? (int) ceil($this->total / $this->perPage) : 0,
        ];
    }
}
