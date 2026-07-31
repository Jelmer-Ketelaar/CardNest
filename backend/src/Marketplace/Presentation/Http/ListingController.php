<?php

declare(strict_types=1);

namespace App\Marketplace\Presentation\Http;

use App\Identity\Domain\Entity\User;
use App\Marketplace\Application\CancelListing;
use App\Marketplace\Application\CreateListing;
use App\Marketplace\Application\Dto\CreateListingRequest;
use App\Marketplace\Application\Dto\ListListingsRequest;
use App\Marketplace\Application\Dto\UpdateListingRequest;
use App\Marketplace\Application\FindListings;
use App\Marketplace\Application\PublishListing;
use App\Marketplace\Application\UpdateListing;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/api/v1/listings')]
final readonly class ListingController
{
    #[Route('', methods: ['GET'])]
    public function list(
        FindListings $finder,
        #[MapQueryString(validationFailedStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY)]
        ListListingsRequest $request = new ListListingsRequest(),
    ): JsonResponse {
        return new JsonResponse($finder->search($request)->toArray());
    }

    #[Route('/{id}', methods: ['GET'])]
    public function get(string $id, FindListings $finder): JsonResponse
    {
        return new JsonResponse($finder->get($id)->toArray());
    }

    #[Route('', methods: ['POST'])]
    public function create(
        #[CurrentUser] User $seller,
        #[MapRequestPayload] CreateListingRequest $request,
        CreateListing $service,
    ): JsonResponse {
        return new JsonResponse($service->create($seller, $request)->toArray(), Response::HTTP_CREATED);
    }

    #[Route('/{id}', methods: ['PATCH'])]
    public function update(
        #[CurrentUser] User $seller,
        string $id,
        #[MapRequestPayload] UpdateListingRequest $request,
        UpdateListing $service,
    ): JsonResponse {
        return new JsonResponse($service->update($seller, $id, $request)->toArray());
    }

    #[Route('/{id}/publish', methods: ['POST'])]
    public function publish(#[CurrentUser] User $seller, string $id, PublishListing $service): JsonResponse
    {
        return new JsonResponse($service->publish($seller, $id)->toArray());
    }

    #[Route('/{id}/cancel', methods: ['POST'])]
    public function cancel(#[CurrentUser] User $seller, string $id, CancelListing $service): JsonResponse
    {
        return new JsonResponse($service->cancel($seller, $id)->toArray());
    }
}
