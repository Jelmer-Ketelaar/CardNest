<?php

declare(strict_types=1);

namespace App\Storage\Application\Dto;

use App\Storage\Domain\Entity\StorageLocation;

final readonly class StorageLocationResponse
{
    public function __construct(
        private StorageLocation $location,
        private int $currentItemCount = 0,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->location->getId()->toRfc4122(),
            'name' => $this->location->getName(),
            'type' => $this->location->getType()->value,
            'parentId' => $this->location->getParent()?->getId()->toRfc4122(),
            'path' => array_map(
                static fn (StorageLocation $location): array => [
                    'id' => $location->getId()->toRfc4122(),
                    'name' => $location->getName(),
                    'type' => $location->getType()->value,
                ],
                $this->location->path(),
            ),
            'currentItemCount' => $this->currentItemCount,
            'createdAt' => $this->location->getCreatedAt()->format(DATE_ATOM),
            'updatedAt' => $this->location->getUpdatedAt()->format(DATE_ATOM),
        ];
    }
}
