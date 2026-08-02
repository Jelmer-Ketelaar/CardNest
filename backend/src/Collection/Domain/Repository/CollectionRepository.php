<?php

declare(strict_types=1);

namespace App\Collection\Domain\Repository;

use App\Collection\Domain\Entity\Collection;
use App\Identity\Domain\Entity\User;

interface CollectionRepository
{
    public function findForOwner(User $owner): ?Collection;

    /**
     * Returns the owner's default collection and schedules a new one for
     * persistence when the owner does not have one yet.
     */
    public function getOrCreateForOwner(User $owner): Collection;
}
