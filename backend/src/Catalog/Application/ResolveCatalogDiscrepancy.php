<?php

declare(strict_types=1);

namespace App\Catalog\Application;

use App\Catalog\Application\Dto\CatalogDiscrepancyResponse;
use App\Catalog\Application\Dto\ResolveCatalogDiscrepancyRequest;
use App\Catalog\Application\Exception\CatalogDiscrepancyNotFound;
use App\Catalog\Domain\Entity\CatalogDiscrepancy;
use App\Catalog\Domain\Entity\CatalogDiscrepancyResolutionStatus;
use App\Catalog\Domain\Repository\CatalogDiscrepancyRepository;
use App\Identity\Domain\Entity\User;

final readonly class ResolveCatalogDiscrepancy
{
    public function __construct(private CatalogDiscrepancyRepository $discrepancies)
    {
    }

    public function execute(
        User $actor,
        string $id,
        ResolveCatalogDiscrepancyRequest $request,
    ): CatalogDiscrepancyResponse {
        $discrepancy = $this->discrepancies->get($id);
        if (!$discrepancy instanceof CatalogDiscrepancy) {
            throw new CatalogDiscrepancyNotFound();
        }

        if ($discrepancy->resolve(
            CatalogDiscrepancyResolutionStatus::from($request->status),
            $request->reason,
            $actor,
        )) {
            $this->discrepancies->save($discrepancy);
        }

        return new CatalogDiscrepancyResponse($discrepancy);
    }
}
