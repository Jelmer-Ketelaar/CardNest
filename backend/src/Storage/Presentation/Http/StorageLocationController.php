<?php

declare(strict_types=1);

namespace App\Storage\Presentation\Http;

use App\Identity\Domain\Entity\User;
use App\Storage\Application\CreateStorageLocation;
use App\Storage\Application\DeleteStorageLocation;
use App\Storage\Application\Dto\CreateStorageLocationRequest;
use App\Storage\Application\Dto\UpdateStorageLocationRequest;
use App\Storage\Application\FindStorageLocations;
use App\Storage\Application\UpdateStorageLocation;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/api/v1/me/storage-locations')]
final readonly class StorageLocationController
{
    #[Route('', methods: ['GET'])]
    public function list(#[CurrentUser] User $owner, FindStorageLocations $finder): JsonResponse
    {
        return new JsonResponse($finder->list($owner)->toArray());
    }

    #[Route('', methods: ['POST'])]
    public function create(
        #[CurrentUser] User $owner,
        #[MapRequestPayload] CreateStorageLocationRequest $request,
        CreateStorageLocation $service,
    ): JsonResponse {
        return new JsonResponse($service->create($owner, $request)->toArray(), Response::HTTP_CREATED);
    }

    #[Route('/{id}', methods: ['GET'])]
    public function get(#[CurrentUser] User $owner, string $id, FindStorageLocations $finder): JsonResponse
    {
        return new JsonResponse($finder->get($owner, $id)->toArray());
    }

    #[Route('/{id}', methods: ['PATCH'])]
    public function update(
        #[CurrentUser] User $owner,
        string $id,
        #[MapRequestPayload] UpdateStorageLocationRequest $request,
        UpdateStorageLocation $service,
    ): JsonResponse {
        return new JsonResponse($service->update($owner, $id, $request)->toArray());
    }

    #[Route('/{id}', methods: ['DELETE'])]
    public function delete(#[CurrentUser] User $owner, string $id, DeleteStorageLocation $service): Response
    {
        $service->delete($owner, $id);

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
