<?php

declare(strict_types=1);

namespace App\Catalog\Presentation\Http;

use App\Catalog\Application\Dto\CatalogCoverageSnapshotResponse;
use App\Catalog\Application\Dto\GenerateCatalogCoverageRequest;
use App\Catalog\Application\Dto\ListCatalogDiscrepanciesRequest;
use App\Catalog\Application\Dto\ResolveCatalogDiscrepancyRequest;
use App\Catalog\Application\FindCatalogDiscrepancies;
use App\Catalog\Application\GenerateCatalogCoverageSnapshot;
use App\Catalog\Application\ResolveCatalogDiscrepancy;
use App\Identity\Domain\Entity\User;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/v1/admin/catalog')]
#[IsGranted('ROLE_ADMIN')]
final readonly class CatalogAdministrationController
{
    #[Route('/discrepancies', methods: ['GET'])]
    public function discrepancies(
        FindCatalogDiscrepancies $finder,
        #[MapQueryString(validationFailedStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY)]
        ListCatalogDiscrepanciesRequest $request = new ListCatalogDiscrepanciesRequest(),
    ): JsonResponse {
        return new JsonResponse($finder->list($request)->toArray());
    }

    #[Route('/discrepancies/{id}', methods: ['PATCH'])]
    public function resolve(
        #[CurrentUser] User $actor,
        string $id,
        #[MapRequestPayload] ResolveCatalogDiscrepancyRequest $request,
        ResolveCatalogDiscrepancy $resolver,
    ): JsonResponse {
        return new JsonResponse($resolver->execute($actor, $id, $request)->toArray());
    }

    #[Route('/coverage-snapshots', methods: ['POST'])]
    public function generateCoverage(
        #[CurrentUser] User $approver,
        #[MapRequestPayload] GenerateCatalogCoverageRequest $request,
        GenerateCatalogCoverageSnapshot $generator,
    ): JsonResponse {
        $snapshot = $generator->execute($approver, $request->importRunId, $request->toManifest());

        return new JsonResponse((new CatalogCoverageSnapshotResponse($snapshot))->toArray(includeEvidenceReference: true));
    }
}
