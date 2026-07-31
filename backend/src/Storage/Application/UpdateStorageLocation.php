<?php

declare(strict_types=1);

namespace App\Storage\Application;

use App\Identity\Domain\Entity\User;
use App\Storage\Application\Dto\StorageLocationResponse;
use App\Storage\Application\Dto\UpdateStorageLocationRequest;
use App\Storage\Domain\Entity\StorageLocationType;
use App\Storage\Domain\Repository\CollectionItemLocationRepository;
use App\Storage\Domain\Repository\StorageLocationRepository;

final readonly class UpdateStorageLocation
{
    public function __construct(
        private StorageLocationResolver $resolver,
        private StorageLocationRepository $locations,
        private CollectionItemLocationRepository $placements,
    ) {
    }

    public function update(User $owner, string $id, UpdateStorageLocationRequest $request): StorageLocationResponse
    {
        $location = $this->resolver->owned($id, $owner);
        if ($request->name !== null) {
            $location->rename($request->name);
        }
        if ($request->type !== null) {
            $location->changeType(StorageLocationType::from($request->type));
        }
        if ($request->clearParent) {
            $location->moveTo(null);
        } elseif ($request->parentId !== null) {
            $location->moveTo($this->resolver->owned($request->parentId, $owner));
        }

        $this->locations->save($location);
        $counts = $this->placements->currentItemCounts($owner);

        return new StorageLocationResponse($location, $counts[$location->getId()->toRfc4122()] ?? 0);
    }
}
