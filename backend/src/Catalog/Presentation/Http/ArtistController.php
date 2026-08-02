<?php

declare(strict_types=1);

namespace App\Catalog\Presentation\Http;

use App\Catalog\Application\ArtistFinder;
use App\Catalog\Application\CatalogCardFinder;
use App\Catalog\Application\Dto\ListArtistsRequest;
use App\Catalog\Application\Dto\SearchCatalogCardsRequest;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/v1/artists')]
final readonly class ArtistController
{
    #[Route('', methods: ['GET'])]
    public function list(
        ArtistFinder $finder,
        #[MapQueryString(validationFailedStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY)]
        ListArtistsRequest $request = new ListArtistsRequest(),
    ): JsonResponse {
        return new JsonResponse($finder->list($request)->toArray());
    }

    #[Route('/suggestions', methods: ['GET'])]
    public function suggestions(
        ArtistFinder $finder,
        #[MapQueryParameter] string $q = '',
        #[MapQueryParameter] int $limit = 10,
    ): JsonResponse {
        return new JsonResponse(['items' => $finder->suggestions($q, max(1, min($limit, 10)))]);
    }

    #[Route('/slug/{slug}', methods: ['GET'])]
    public function getBySlug(string $slug, ArtistFinder $finder): JsonResponse
    {
        $artist = $finder->getBySlug($slug);
        if ($artist === null) {
            throw new NotFoundHttpException('Artist niet gevonden.');
        }

        return new JsonResponse($artist);
    }

    #[Route('/{id}', methods: ['GET'])]
    public function get(string $id, ArtistFinder $finder): JsonResponse
    {
        $artist = $finder->get($id);
        if ($artist === null) {
            throw new NotFoundHttpException('Artist niet gevonden.');
        }

        return new JsonResponse($artist);
    }

    #[Route('/{id}/cards', methods: ['GET'])]
    public function cards(
        string $id,
        ArtistFinder $artistFinder,
        CatalogCardFinder $cardFinder,
        #[MapQueryString(validationFailedStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY)]
        SearchCatalogCardsRequest $request = new SearchCatalogCardsRequest(),
    ): JsonResponse {
        if ($artistFinder->get($id) === null) {
            throw new NotFoundHttpException('Artist niet gevonden.');
        }

        $artistRequest = new SearchCatalogCardsRequest(
            setId: $request->setId,
            seriesId: $request->seriesId,
            name: $request->name,
            number: $request->number,
            rarity: $request->rarity,
            variant: $request->variant,
            artistIds: [$id],
            page: $request->page,
            perPage: $request->perPage,
        );

        return new JsonResponse($cardFinder->search($artistRequest)->toArray());
    }
}
