<?php

declare(strict_types=1);

namespace App\Catalog\Presentation\Http;

use App\Catalog\Application\CardSetFinder;
use App\Catalog\Application\Dto\CardSetResponse;
use App\Catalog\Application\Dto\ListCardSetsRequest;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/v1/card-sets')]
final readonly class CardSetController
{
    #[Route('', methods: ['GET'])]
    public function list(
        CardSetFinder $finder,
        #[MapQueryString(validationFailedStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY)]
        ListCardSetsRequest $request = new ListCardSetsRequest(),
    ): JsonResponse {
        return new JsonResponse($finder->list($request)->toArray());
    }

    #[Route('/{id}', methods: ['GET'])]
    public function get(string $id, CardSetFinder $finder): JsonResponse
    {
        $response = $finder->get($id);
        if (!$response instanceof CardSetResponse) {
            throw new NotFoundHttpException('Set niet gevonden.');
        }

        return new JsonResponse($response->toArray());
    }
}
