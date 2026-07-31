<?php

declare(strict_types=1);

namespace App\Storage\Application;

use App\Identity\Domain\Entity\User;
use App\Storage\Application\Dto\StorageLocationCollectionResponse;
use App\Storage\Application\Dto\StorageLocationResponse;
use App\Storage\Domain\Repository\CollectionItemLocationRepository;
use App\Storage\Domain\Repository\StorageLocationRepository;

final readonly class FindStorageLocations
{
    public function __construct(
        private StorageLocationResolver $resolver,
        private StorageLocationRepository $locations,
        private CollectionItemLocationRepository $placements,
    ) {
    }

    public function list(User $owner): StorageLocationCollectionResponse
    {
        return new StorageLocationCollectionResponse(
            $this->locations->findAllForOwner($owner),
            $this->placements->currentItemCounts($owner),
        );
    }

    public function get(User $owner, string $id): StorageLocationResponse
    {
        $location = $this->resolver->owned($id, $owner);
        $counts = $this->placements->currentItemCounts($owner);

        return new StorageLocationResponse($location, $counts[$location->getId()->toRfc4122()] ?? 0);
    }
}
