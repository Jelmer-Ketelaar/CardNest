<?php

declare(strict_types=1);

namespace App\Catalog\Presentation\Http;

use App\Catalog\Application\Dto\CatalogCoverageQuery;
use App\Catalog\Application\FindCatalogCoverageSnapshot;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;

final readonly class CatalogCoverageController
{
    #[Route('/api/v1/catalog/coverage', methods: ['GET'])]
    public function __invoke(
        FindCatalogCoverageSnapshot $finder,
        #[MapQueryString(validationFailedStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY)]
        CatalogCoverageQuery $query = new CatalogCoverageQuery(),
    ): JsonResponse {
        return new JsonResponse($finder->latest($query->provider, $query->region, $query->language));
    }
}
