<?php

declare(strict_types=1);

namespace App\Order\Presentation\Http;

use App\Identity\Domain\Entity\User;
use App\Order\Application\CancelOrder;
use App\Order\Application\Dto\CancelOrderRequest;
use App\Order\Application\Dto\ImportOrderItemRequest;
use App\Order\Application\FindOrders;
use App\Order\Application\ImportOrderItemToCollection;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/api/v1/me/orders')]
final readonly class OrderController
{
    #[Route('', methods: ['GET'])]
    public function list(#[CurrentUser] User $participant, FindOrders $finder): JsonResponse
    {
        return new JsonResponse($finder->list($participant)->toArray());
    }

    #[Route('/{id}', methods: ['GET'])]
    public function get(#[CurrentUser] User $participant, string $id, FindOrders $finder): JsonResponse
    {
        return new JsonResponse($finder->get($participant, $id)->toArray());
    }

    #[Route('/{id}/cancel', methods: ['POST'])]
    public function cancel(
        #[CurrentUser] User $participant,
        string $id,
        #[MapRequestPayload] CancelOrderRequest $request,
        CancelOrder $service,
    ): JsonResponse {
        return new JsonResponse($service->cancel($participant, $id, $request)->toArray());
    }

    #[Route('/{id}/items/{itemId}/import-to-collection', methods: ['POST'])]
    public function importToCollection(
        #[CurrentUser] User $buyer,
        string $id,
        string $itemId,
        #[MapRequestPayload] ImportOrderItemRequest $request,
        ImportOrderItemToCollection $service,
    ): JsonResponse {
        return new JsonResponse($service->import($buyer, $id, $itemId, $request)->toArray());
    }
}
