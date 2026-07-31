<?php

declare(strict_types=1);

namespace App\Storage\Application;

use App\Identity\Domain\Entity\User;
use App\Storage\Application\Exception\StorageLocationNotFound;
use App\Storage\Domain\Entity\StorageLocation;
use App\Storage\Domain\Repository\StorageLocationRepository;

final readonly class StorageLocationResolver
{
    public function __construct(private StorageLocationRepository $locations)
    {
    }

    public function owned(string $id, User $owner): StorageLocation
    {
        $location = $this->locations->getForOwner($id, $owner);
        if (!$location instanceof StorageLocation) {
            throw new StorageLocationNotFound('Opslaglocatie niet gevonden.');
        }

        return $location;
    }

    public function optionalOwned(?string $id, User $owner): ?StorageLocation
    {
        return $id === null ? null : $this->owned($id, $owner);
    }
}
