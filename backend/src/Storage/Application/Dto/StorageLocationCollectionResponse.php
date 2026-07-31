<?php

declare(strict_types=1);

namespace App\Storage\Application\Dto;

use App\Storage\Domain\Entity\StorageLocation;

final readonly class StorageLocationCollectionResponse
{
    /**
     * @param list<StorageLocation> $locations
     * @param array<string, int>    $itemCounts
     */
    public function __construct(
        private array $locations,
        private array $itemCounts,
    ) {
    }

    /** @return array{items: list<array<string, mixed>>, total: int} */
    public function toArray(): array
    {
        return [
            'items' => array_map(
                fn (StorageLocation $location): array => (new StorageLocationResponse(
                    $location,
                    $this->itemCounts[$location->getId()->toRfc4122()] ?? 0,
                ))->toArray(),
                $this->locations,
            ),
            'total' => count($this->locations),
        ];
    }
}
