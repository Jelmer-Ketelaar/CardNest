<?php

declare(strict_types=1);

namespace App\Catalog\Application\Dto;

final readonly class ArtistCollectionResponse
{
    /** @param list<array<string, mixed>> $items */
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
            'items' => $this->items,
            'total' => $this->total,
            'page' => $this->page,
            'perPage' => $this->perPage,
            'totalPages' => $this->total === 0 ? 0 : (int) ceil($this->total / $this->perPage),
        ];
    }
}
