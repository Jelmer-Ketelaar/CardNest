<?php

declare(strict_types=1);

namespace App\Storage\Application;

use App\Identity\Domain\Entity\User;
use App\Storage\Application\Dto\CreateStorageLocationRequest;
use App\Storage\Application\Dto\StorageLocationResponse;
use App\Storage\Domain\Entity\StorageLocation;
use App\Storage\Domain\Entity\StorageLocationType;
use App\Storage\Domain\Repository\StorageLocationRepository;

final readonly class CreateStorageLocation
{
    public function __construct(
        private StorageLocationResolver $resolver,
        private StorageLocationRepository $locations,
    ) {
    }

    public function create(User $owner, CreateStorageLocationRequest $request): StorageLocationResponse
    {
        $location = new StorageLocation(
            owner: $owner,
            name: $request->name,
            type: StorageLocationType::from($request->type),
            parent: $this->resolver->optionalOwned($request->parentId, $owner),
        );
        $this->locations->save($location);

        return new StorageLocationResponse($location);
    }
}
