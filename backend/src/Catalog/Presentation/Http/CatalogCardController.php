<?php

declare(strict_types=1);

namespace App\Catalog\Presentation\Http;

use App\Catalog\Application\CatalogCardFinder;
use App\Catalog\Application\Dto\CatalogCardResponse;
use App\Catalog\Application\Dto\SearchCatalogCardsRequest;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/v1/cards')]
final readonly class CatalogCardController
{
    #[Route('', methods: ['GET'])]
    public function search(
        CatalogCardFinder $finder,
        #[MapQueryString] SearchCatalogCardsRequest $request = new SearchCatalogCardsRequest(),
    ): JsonResponse {
        return new JsonResponse($finder->search($request)->toArray());
    }

    #[Route('/{id}', methods: ['GET'])]
    public function get(string $id, CatalogCardFinder $finder): JsonResponse
    {
        $response = $finder->get($id);
        if (!$response instanceof CatalogCardResponse) {
            throw new NotFoundHttpException('Kaart niet gevonden.');
        }

        return new JsonResponse($response->toArray());
    }
}
