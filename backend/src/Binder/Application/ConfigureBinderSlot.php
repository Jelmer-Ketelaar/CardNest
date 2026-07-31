<?php

declare(strict_types=1);

namespace App\Binder\Application;

use App\Binder\Application\Dto\BinderDetailResponse;
use App\Binder\Application\Dto\ConfigureBinderSlotRequest;
use App\Binder\Domain\Repository\BinderRepository;
use App\Catalog\Domain\Entity\CatalogCard;
use App\Catalog\Domain\Repository\CatalogCardRepository;
use App\Collection\Application\Exception\CatalogCardUnavailable;
use App\Identity\Domain\Entity\User;

final readonly class ConfigureBinderSlot
{
    public function __construct(
        private BinderResolver $resolver,
        private CatalogCardRepository $catalogCards,
        private BinderRepository $binders,
        private FindBinders $finder,
    ) {
    }

    public function configure(
        User $owner,
        string $binderId,
        string $slotId,
        ConfigureBinderSlotRequest $request,
    ): BinderDetailResponse {
        $slot = $this->resolver->ownedSlot($binderId, $slotId, $owner);
        if ($request->clear) {
            $slot->clearPlaceholder();
        } elseif ($request->catalogCardId !== null) {
            $card = $this->catalogCards->get($request->catalogCardId);
            if (!$card instanceof CatalogCard) {
                throw new CatalogCardUnavailable('De gekozen cataloguskaart bestaat niet.');
            }
            $slot->reserveCatalogCard($card);
        } elseif ($request->label !== null) {
            $slot->setPlaceholderLabel($request->label);
        }

        $this->binders->saveSlot($slot);

        return $this->finder->get($owner, $binderId);
    }
}
