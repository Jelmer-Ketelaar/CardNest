<?php

declare(strict_types=1);

namespace App\Binder\Application;

use App\Binder\Application\Dto\AssignBinderSlotRequest;
use App\Binder\Application\Dto\BinderDetailResponse;
use App\Binder\Domain\Exception\BinderSlotReservationMismatch;
use App\Collection\Application\FindCollectionItems;
use App\Collection\Domain\Entity\CollectionItemStatus;
use App\Collection\Domain\Exception\ArchivedCollectionItemCannotBeChanged;
use App\Identity\Domain\Entity\User;
use App\Storage\Domain\Repository\CollectionItemLocationRepository;

final readonly class AssignBinderSlot
{
    public function __construct(
        private BinderResolver $resolver,
        private FindCollectionItems $collectionItems,
        private CollectionItemLocationRepository $placements,
        private FindBinders $binders,
    ) {
    }

    public function assign(
        User $owner,
        string $binderId,
        string $slotId,
        AssignBinderSlotRequest $request,
    ): BinderDetailResponse {
        $slot = $this->resolver->ownedSlot($binderId, $slotId, $owner);
        $item = $this->collectionItems->findOwnedItem($owner, $request->collectionItemId);
        if ($item->getStatus() === CollectionItemStatus::Archived) {
            throw new ArchivedCollectionItemCannotBeChanged('Een gearchiveerde kaart kan niet in een binder worden geplaatst.');
        }

        $reservedCard = $slot->getReservedCatalogCard();
        if ($reservedCard !== null && !$reservedCard->getId()->equals($item->getCatalogCard()->getId())) {
            throw new BinderSlotReservationMismatch('Dit vak is voor een andere cataloguskaart gereserveerd.');
        }

        $this->placements->placeInBinderSlot($item, $slot);

        return $this->binders->get($owner, $binderId);
    }

    public function unassign(User $owner, string $binderId, string $slotId): BinderDetailResponse
    {
        $slot = $this->resolver->ownedSlot($binderId, $slotId, $owner);
        $current = $this->placements->currentForBinderSlot($slot);
        if ($current !== null) {
            $this->placements->removeCurrent($current->getCollectionItem());
        }

        return $this->binders->get($owner, $binderId);
    }
}
