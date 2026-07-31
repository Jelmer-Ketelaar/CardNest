<?php

declare(strict_types=1);

namespace App\Storage\Domain\Repository;

use App\Identity\Domain\Entity\User;
use App\Storage\Domain\Entity\StorageLocation;

interface StorageLocationRepository
{
    /** @return list<StorageLocation> */
    public function findAllForOwner(User $owner): array;

    public function getForOwner(string $id, User $owner): ?StorageLocation;

    public function save(StorageLocation $location): void;
}
