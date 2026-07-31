<?php

declare(strict_types=1);

namespace App\Collection\Presentation\Http;

use App\Collection\Application\ArchiveCollectionItem;
use App\Collection\Application\CreateCollectionItems;
use App\Collection\Application\Dto\CreateCollectionItemRequest;
use App\Collection\Application\Dto\ListCollectionItemsRequest;
use App\Collection\Application\Dto\UpdateCollectionItemRequest;
use App\Collection\Application\FindCollectionItems;
use App\Collection\Application\UpdateCollectionItem;
use App\Identity\Domain\Entity\User;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/api/v1/me/collection-items')]
final readonly class CollectionItemController
{
    #[Route('', methods: ['GET'])]
    public function list(
        #[CurrentUser] User $owner,
        FindCollectionItems $finder,
        #[MapQueryString(validationFailedStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY)]
        ListCollectionItemsRequest $request = new ListCollectionItemsRequest(),
    ): JsonResponse {
        return new JsonResponse($finder->list($owner, $request)->toArray());
    }

    #[Route('', methods: ['POST'])]
    public function create(
        #[CurrentUser] User $owner,
        #[MapRequestPayload] CreateCollectionItemRequest $request,
        CreateCollectionItems $service,
    ): JsonResponse {
        return new JsonResponse($service->create($owner, $request)->toArray(), Response::HTTP_CREATED);
    }

    #[Route('/{id}', methods: ['GET'])]
    public function get(#[CurrentUser] User $owner, string $id, FindCollectionItems $finder): JsonResponse
    {
        return new JsonResponse($finder->get($owner, $id)->toArray());
    }

    #[Route('/{id}', methods: ['PATCH'])]
    public function update(
        #[CurrentUser] User $owner,
        string $id,
        #[MapRequestPayload] UpdateCollectionItemRequest $request,
        UpdateCollectionItem $service,
    ): JsonResponse {
        return new JsonResponse($service->update($owner, $id, $request)->toArray());
    }

    #[Route('/{id}', methods: ['DELETE'])]
    public function archive(
        #[CurrentUser] User $owner,
        string $id,
        ArchiveCollectionItem $service,
    ): Response {
        $service->archive($owner, $id);

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
