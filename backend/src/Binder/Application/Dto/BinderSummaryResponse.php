<?php

declare(strict_types=1);

namespace App\Binder\Application\Dto;

use App\Binder\Domain\Entity\Binder;
use App\Storage\Application\Dto\StorageLocationResponse;

final readonly class BinderSummaryResponse
{
    public function __construct(
        private Binder $binder,
        private int $occupiedSlots,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->binder->getId()->toRfc4122(),
            'name' => $this->binder->getName(),
            'rows' => $this->binder->getRows(),
            'columns' => $this->binder->getColumns(),
            'pageCount' => $this->binder->getPageCount(),
            'surfaceCount' => $this->binder->getSurfaceCount(),
            'doubleSided' => $this->binder->isDoubleSided(),
            'layoutStrategy' => $this->binder->getLayoutStrategy()->value,
            'slotCount' => $this->binder->getSlotCount(),
            'occupiedSlots' => $this->occupiedSlots,
            'parentLocation' => $this->binder->getParentLocation() !== null
                ? (new StorageLocationResponse($this->binder->getParentLocation()))->toArray()
                : null,
            'createdAt' => $this->binder->getCreatedAt()->format(DATE_ATOM),
            'updatedAt' => $this->binder->getUpdatedAt()->format(DATE_ATOM),
        ];
    }
}
