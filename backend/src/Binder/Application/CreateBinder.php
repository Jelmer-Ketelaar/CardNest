<?php

declare(strict_types=1);

namespace App\Binder\Application;

use App\Binder\Application\Dto\BinderDetailResponse;
use App\Binder\Application\Dto\CreateBinderRequest;
use App\Binder\Domain\Entity\Binder;
use App\Binder\Domain\Entity\BinderLayoutStrategy;
use App\Binder\Domain\Repository\BinderRepository;
use App\Identity\Domain\Entity\User;
use App\Storage\Application\StorageLocationResolver;

final readonly class CreateBinder
{
    public function __construct(
        private StorageLocationResolver $storageLocations,
        private BinderRepository $binders,
    ) {
    }

    public function create(User $owner, CreateBinderRequest $request): BinderDetailResponse
    {
        $binder = new Binder(
            owner: $owner,
            name: $request->name,
            rows: $request->rows,
            columns: $request->columns,
            pageCount: $request->pageCount,
            doubleSided: $request->doubleSided,
            layoutStrategy: BinderLayoutStrategy::from($request->layoutStrategy),
            parentLocation: $this->storageLocations->optionalOwned($request->parentLocationId, $owner),
        );
        $this->binders->save($binder);

        return new BinderDetailResponse($binder, []);
    }
}
