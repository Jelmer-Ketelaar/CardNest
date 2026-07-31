<?php

declare(strict_types=1);

namespace App\Marketplace\Application\Dto;

use App\Marketplace\Domain\Entity\Listing;
use App\Marketplace\Domain\Repository\ListingSearchResult;

final readonly class ListingCollectionResponse
{
    /** @param list<Listing> $items */
    public function __construct(
        private array $items,
        private int $page,
        private int $perPage,
        private int $total,
        private bool $includePrivateFields = false,
    ) {
    }

    public static function fromSearchResult(ListingSearchResult $result): self
    {
        return new self($result->items, $result->page, $result->perPage, $result->total);
    }

    /** @param list<Listing> $listings */
    public static function forSeller(array $listings): self
    {
        return new self($listings, 1, max(1, count($listings)), count($listings), true);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'items' => array_map(
                fn (Listing $listing): array => (new ListingResponse(
                    $listing,
                    $this->includePrivateFields,
                ))->toArray(),
                $this->items,
            ),
            'page' => $this->page,
            'perPage' => $this->perPage,
            'total' => $this->total,
            'totalPages' => $this->total === 0 ? 0 : (int) ceil($this->total / $this->perPage),
        ];
    }
}
