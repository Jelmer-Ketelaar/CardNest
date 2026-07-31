<?php

declare(strict_types=1);

namespace App\Binder\Application;

use App\Binder\Application\Dto\BinderDetailResponse;
use App\Binder\Application\Dto\UpdateBinderRequest;
use App\Binder\Domain\Entity\BinderLayoutStrategy;
use App\Binder\Domain\Repository\BinderRepository;
use App\Identity\Domain\Entity\User;
use App\Storage\Application\StorageLocationResolver;

final readonly class UpdateBinder
{
    public function __construct(
        private BinderResolver $resolver,
        private StorageLocationResolver $storageLocations,
        private BinderRepository $binders,
        private FindBinders $finder,
    ) {
    }

    public function update(User $owner, string $id, UpdateBinderRequest $request): BinderDetailResponse
    {
        $binder = $this->resolver->owned($id, $owner);
        if ($request->name !== null) {
            $binder->rename($request->name);
        }
        if ($request->layoutStrategy !== null) {
            $binder->changeLayoutStrategy(BinderLayoutStrategy::from($request->layoutStrategy));
        }
        if ($request->clearParentLocation) {
            $binder->moveTo(null);
        } elseif ($request->parentLocationId !== null) {
            $binder->moveTo($this->storageLocations->owned($request->parentLocationId, $owner));
        }

        $this->binders->save($binder);

        return $this->finder->response($binder);
    }
}
