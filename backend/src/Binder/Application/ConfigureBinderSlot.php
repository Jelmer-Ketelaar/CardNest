<?php

declare(strict_types=1);

namespace App\Binder\Application;

use App\Binder\Application\Dto\BinderDetailResponse;
use App\Binder\Application\Dto\ConfigureBinderSlotRequest;
use App\Binder\Domain\Exception\BinderSlotOccupied;
use App\Binder\Domain\Repository\BinderRepository;
use App\Catalog\Domain\Entity\CatalogCard;
use App\Catalog\Domain\Repository\CatalogCardRepository;
use App\Collection\Application\Exception\CatalogCardUnavailable;
use App\Identity\Domain\Entity\User;
use App\Shared\Domain\Transaction\TransactionManager;
use App\Storage\Domain\Repository\CollectionItemLocationRepository;

final readonly class ConfigureBinderSlot
{
    public function __construct(
        private BinderResolver $resolver,
        private CatalogCardRepository $catalogCards,
        private BinderRepository $binders,
        private FindBinders $finder,
        private CollectionItemLocationRepository $placements,
        private TransactionManager $transactions,
    ) {
    }

    public function configure(
        User $owner,
        string $binderId,
        string $slotId,
        ConfigureBinderSlotRequest $request,
    ): BinderDetailResponse {
        $this->transactions->run(function () use ($owner, $binderId, $slotId, $request): void {
            $slot = $this->resolver->lockedOwnedSlot($binderId, $slotId, $owner);
            if ($request->clear) {
                $slot->clearPlaceholder();
            } else {
                if ($this->placements->currentForBinderSlot($slot) !== null) {
                    throw new BinderSlotOccupied('Een bezet bindervak kan geen placeholder bevatten.');
                }

                if ($request->catalogCardId !== null) {
                    $card = $this->catalogCards->get($request->catalogCardId);
                    if (!$card instanceof CatalogCard) {
                        throw new CatalogCardUnavailable('De gekozen cataloguskaart bestaat niet.');
                    }
                    $slot->reserveCatalogCard($card);
                } elseif ($request->label !== null) {
                    $slot->setPlaceholderLabel($request->label);
                }
            }

            $this->binders->saveSlot($slot);
        });

        return $this->finder->get($owner, $binderId);
    }
}
