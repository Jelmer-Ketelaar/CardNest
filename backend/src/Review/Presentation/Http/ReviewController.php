<?php

declare(strict_types=1);

namespace App\Review\Presentation\Http;

use App\Identity\Domain\Entity\User;
use App\Review\Application\CreateReview;
use App\Review\Application\Dto\CreateReviewRequest;
use App\Review\Application\FindReviews;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

final readonly class ReviewController
{
    #[Route('/api/v1/me/reviews', methods: ['GET'])]
    public function authored(#[CurrentUser] User $reviewer, FindReviews $finder): JsonResponse
    {
        return new JsonResponse($finder->authored($reviewer)->toArray());
    }

    #[Route('/api/v1/me/orders/{orderId}/reviews', methods: ['POST'])]
    public function create(
        #[CurrentUser] User $reviewer,
        string $orderId,
        #[MapRequestPayload] CreateReviewRequest $request,
        CreateReview $service,
    ): JsonResponse {
        return new JsonResponse(
            $service->create($reviewer, $orderId, $request)->toArray(),
            Response::HTTP_CREATED,
        );
    }
}
