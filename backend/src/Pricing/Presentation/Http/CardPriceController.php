<?php

declare(strict_types=1);

namespace App\Pricing\Presentation\Http;

use App\Pricing\Application\Dto\CardPriceHistoryRequest;
use App\Pricing\Application\Dto\CardPriceHistoryResponse;
use App\Pricing\Application\FindCardPriceHistory;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/v1/cards/{id}/prices')]
final readonly class CardPriceController
{
    #[Route('', methods: ['GET'])]
    public function get(
        string $id,
        FindCardPriceHistory $finder,
        #[MapQueryString(validationFailedStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY)]
        CardPriceHistoryRequest $request = new CardPriceHistoryRequest(),
    ): JsonResponse {
        $response = $finder->find($id, $request->days);
        if (!$response instanceof CardPriceHistoryResponse) {
            throw new NotFoundHttpException('Kaart niet gevonden.');
        }

        return new JsonResponse($response->toArray());
    }
}
