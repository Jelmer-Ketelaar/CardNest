<?php

declare(strict_types=1);

namespace App\Binder\Domain\Repository;

use App\Binder\Domain\Entity\Binder;
use App\Binder\Domain\Entity\BinderSlot;
use App\Identity\Domain\Entity\User;

interface BinderRepository
{
    /** @return list<Binder> */
    public function findAllForOwner(User $owner): array;

    public function getForOwner(string $id, User $owner): ?Binder;

    public function getSlotForOwner(string $binderId, string $slotId, User $owner): ?BinderSlot;

    public function lockSlotForOwner(string $binderId, string $slotId, User $owner): ?BinderSlot;

    public function save(Binder $binder): void;

    public function saveSlot(BinderSlot $slot): void;
}
