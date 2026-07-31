<?php

declare(strict_types=1);

namespace App\Binder\Presentation\Http;

use App\Binder\Application\AssignBinderSlot;
use App\Binder\Application\ConfigureBinderSlot;
use App\Binder\Application\CreateBinder;
use App\Binder\Application\Dto\AssignBinderSlotRequest;
use App\Binder\Application\Dto\ConfigureBinderSlotRequest;
use App\Binder\Application\Dto\CreateBinderRequest;
use App\Binder\Application\Dto\UpdateBinderRequest;
use App\Binder\Application\FindBinders;
use App\Binder\Application\UpdateBinder;
use App\Identity\Domain\Entity\User;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/api/v1/me/binders')]
final readonly class BinderController
{
    #[Route('', methods: ['GET'])]
    public function list(#[CurrentUser] User $owner, FindBinders $finder): JsonResponse
    {
        return new JsonResponse($finder->list($owner)->toArray());
    }

    #[Route('', methods: ['POST'])]
    public function create(
        #[CurrentUser] User $owner,
        #[MapRequestPayload] CreateBinderRequest $request,
        CreateBinder $service,
    ): JsonResponse {
        return new JsonResponse($service->create($owner, $request)->toArray(), Response::HTTP_CREATED);
    }

    #[Route('/{id}', methods: ['GET'])]
    public function get(#[CurrentUser] User $owner, string $id, FindBinders $finder): JsonResponse
    {
        return new JsonResponse($finder->get($owner, $id)->toArray());
    }

    #[Route('/{id}', methods: ['PATCH'])]
    public function update(
        #[CurrentUser] User $owner,
        string $id,
        #[MapRequestPayload] UpdateBinderRequest $request,
        UpdateBinder $service,
    ): JsonResponse {
        return new JsonResponse($service->update($owner, $id, $request)->toArray());
    }

    #[Route('/{id}/slots/{slotId}/assign', methods: ['POST'])]
    public function assign(
        #[CurrentUser] User $owner,
        string $id,
        string $slotId,
        #[MapRequestPayload] AssignBinderSlotRequest $request,
        AssignBinderSlot $service,
    ): JsonResponse {
        return new JsonResponse($service->assign($owner, $id, $slotId, $request)->toArray());
    }

    #[Route('/{id}/slots/{slotId}/assignment', methods: ['DELETE'])]
    public function unassign(
        #[CurrentUser] User $owner,
        string $id,
        string $slotId,
        AssignBinderSlot $service,
    ): JsonResponse {
        return new JsonResponse($service->unassign($owner, $id, $slotId)->toArray());
    }

    #[Route('/{id}/slots/{slotId}/placeholder', methods: ['PATCH'])]
    public function placeholder(
        #[CurrentUser] User $owner,
        string $id,
        string $slotId,
        #[MapRequestPayload] ConfigureBinderSlotRequest $request,
        ConfigureBinderSlot $service,
    ): JsonResponse {
        return new JsonResponse($service->configure($owner, $id, $slotId, $request)->toArray());
    }
}
