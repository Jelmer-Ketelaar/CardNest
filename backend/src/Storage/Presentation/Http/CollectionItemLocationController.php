<?php

declare(strict_types=1);

namespace App\Storage\Presentation\Http;

use App\Identity\Domain\Entity\User;
use App\Storage\Application\Dto\PlaceCollectionItemRequest;
use App\Storage\Application\ManageCollectionItemLocation;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/api/v1/me/collection-items/{itemId}')]
final readonly class CollectionItemLocationController
{
    #[Route('/location', methods: ['GET'])]
    public function get(
        #[CurrentUser] User $owner,
        string $itemId,
        ManageCollectionItemLocation $service,
    ): JsonResponse {
        return new JsonResponse($service->get($owner, $itemId, false)->toArray());
    }

    #[Route('/location-history', methods: ['GET'])]
    public function history(
        #[CurrentUser] User $owner,
        string $itemId,
        ManageCollectionItemLocation $service,
    ): JsonResponse {
        return new JsonResponse($service->get($owner, $itemId, true)->toArray());
    }

    #[Route('/location', methods: ['POST'])]
    public function place(
        #[CurrentUser] User $owner,
        string $itemId,
        #[MapRequestPayload] PlaceCollectionItemRequest $request,
        ManageCollectionItemLocation $service,
    ): JsonResponse {
        return new JsonResponse($service->place($owner, $itemId, $request)->toArray());
    }

    #[Route('/location', methods: ['DELETE'])]
    public function remove(
        #[CurrentUser] User $owner,
        string $itemId,
        ManageCollectionItemLocation $service,
    ): Response {
        $service->remove($owner, $itemId);

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
