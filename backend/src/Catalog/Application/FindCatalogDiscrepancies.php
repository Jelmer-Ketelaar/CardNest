<?php

declare(strict_types=1);

namespace App\Catalog\Application;

use App\Catalog\Application\Dto\CatalogDiscrepancyCollectionResponse;
use App\Catalog\Application\Dto\ListCatalogDiscrepanciesRequest;
use App\Catalog\Domain\Repository\CatalogDiscrepancyRepository;

final readonly class FindCatalogDiscrepancies
{
    public function __construct(private CatalogDiscrepancyRepository $discrepancies)
    {
    }

    public function list(ListCatalogDiscrepanciesRequest $request): CatalogDiscrepancyCollectionResponse
    {
        return new CatalogDiscrepancyCollectionResponse(
            $this->discrepancies->search($request->toCriteria()),
            $request->page,
            $request->perPage,
        );
    }
}
