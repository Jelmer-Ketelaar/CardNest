<?php

declare(strict_types=1);

namespace App\Binder\Application;

use App\Binder\Application\Dto\BinderCollectionResponse;
use App\Binder\Application\Dto\BinderDetailResponse;
use App\Binder\Domain\Entity\Binder;
use App\Binder\Domain\Repository\BinderRepository;
use App\Identity\Domain\Entity\User;
use App\Storage\Domain\Repository\CollectionItemLocationRepository;

final readonly class FindBinders
{
    public function __construct(
        private BinderResolver $resolver,
        private BinderRepository $binders,
        private CollectionItemLocationRepository $placements,
    ) {
    }

    public function list(User $owner): BinderCollectionResponse
    {
        $binders = $this->binders->findAllForOwner($owner);
        $counts = [];
        foreach ($binders as $binder) {
            $counts[$binder->getId()->toRfc4122()] = count($this->placements->currentItemsForBinder($binder));
        }

        return new BinderCollectionResponse($binders, $counts);
    }

    public function get(User $owner, string $id): BinderDetailResponse
    {
        return $this->response($this->resolver->owned($id, $owner));
    }

    public function response(Binder $binder): BinderDetailResponse
    {
        return new BinderDetailResponse($binder, $this->placements->currentItemsForBinder($binder));
    }
}
