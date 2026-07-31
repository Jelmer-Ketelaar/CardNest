<?php

declare(strict_types=1);

namespace App\Binder\Application;

use App\Binder\Application\Exception\BinderNotFound;
use App\Binder\Application\Exception\BinderSlotNotFound;
use App\Binder\Domain\Entity\Binder;
use App\Binder\Domain\Entity\BinderSlot;
use App\Binder\Domain\Repository\BinderRepository;
use App\Identity\Domain\Entity\User;

final readonly class BinderResolver
{
    public function __construct(private BinderRepository $binders)
    {
    }

    public function owned(string $id, User $owner): Binder
    {
        $binder = $this->binders->getForOwner($id, $owner);
        if (!$binder instanceof Binder) {
            throw new BinderNotFound('Binder niet gevonden.');
        }

        return $binder;
    }

    public function ownedSlot(string $binderId, string $slotId, User $owner): BinderSlot
    {
        $slot = $this->binders->getSlotForOwner($binderId, $slotId, $owner);
        if (!$slot instanceof BinderSlot) {
            throw new BinderSlotNotFound('Bindervak niet gevonden.');
        }

        return $slot;
    }
}
