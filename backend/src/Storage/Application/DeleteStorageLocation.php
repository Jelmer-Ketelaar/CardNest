<?php

declare(strict_types=1);

namespace App\Storage\Application;

use App\Identity\Domain\Entity\User;
use App\Storage\Domain\Exception\StorageLocationInUse;
use App\Storage\Domain\Repository\StorageLocationRepository;

final readonly class DeleteStorageLocation
{
    public function __construct(
        private StorageLocationResolver $resolver,
        private StorageLocationRepository $locations,
    ) {
    }

    public function delete(User $owner, string $id): void
    {
        $location = $this->resolver->owned($id, $owner);
        if (!$this->locations->isRemovable($location)) {
            throw new StorageLocationInUse('Een opslaglocatie met sublocaties, binders of locatiehistorie kan niet worden verwijderd.');
        }

        $this->locations->remove($location);
    }
}
