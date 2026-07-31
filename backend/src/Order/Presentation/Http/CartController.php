<?php

declare(strict_types=1);

namespace App\Order\Presentation\Http;

use App\Identity\Domain\Entity\User;
use App\Order\Application\AddCartItem;
use App\Order\Application\CheckoutCart;
use App\Order\Application\Dto\AddCartItemRequest;
use App\Order\Application\Dto\CheckoutCartRequest;
use App\Order\Application\FindCart;
use App\Order\Application\RemoveCartItem;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/api/v1/me/cart')]
final readonly class CartController
{
    #[Route('', methods: ['GET'])]
    public function get(#[CurrentUser] User $buyer, FindCart $finder): JsonResponse
    {
        return new JsonResponse($finder->get($buyer)->toArray());
    }

    #[Route('/items', methods: ['POST'])]
    public function addItem(
        #[CurrentUser] User $buyer,
        #[MapRequestPayload] AddCartItemRequest $request,
        AddCartItem $service,
    ): JsonResponse {
        return new JsonResponse($service->add($buyer, $request)->toArray(), Response::HTTP_CREATED);
    }

    #[Route('/items/{id}', methods: ['DELETE'])]
    public function removeItem(#[CurrentUser] User $buyer, string $id, RemoveCartItem $service): JsonResponse
    {
        return new JsonResponse($service->remove($buyer, $id)->toArray());
    }

    #[Route('/checkout', methods: ['POST'])]
    public function checkout(
        #[CurrentUser] User $buyer,
        #[MapRequestPayload] CheckoutCartRequest $request,
        CheckoutCart $service,
    ): JsonResponse {
        return new JsonResponse($service->checkout($buyer, $request)->toArray());
    }
}
