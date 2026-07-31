<?php

declare(strict_types=1);

namespace App\Review\Presentation\Http;

use App\Review\Application\Dto\ListUserReviewsRequest;
use App\Review\Application\FindReviews;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;

final readonly class ReputationController
{
    #[Route('/api/v1/users/{id}/reputation', methods: ['GET'])]
    public function get(
        string $id,
        FindReviews $finder,
        #[MapQueryString(validationFailedStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY)]
        ListUserReviewsRequest $request = new ListUserReviewsRequest(),
    ): JsonResponse {
        return new JsonResponse($finder->reputation($id, $request)->toArray());
    }
}
