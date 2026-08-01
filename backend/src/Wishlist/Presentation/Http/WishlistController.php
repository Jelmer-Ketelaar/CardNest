<?php

declare(strict_types=1);

namespace App\Wishlist\Presentation\Http;

use App\Identity\Domain\Entity\User;
use App\Wishlist\Application\AddWishlistItem;
use App\Wishlist\Application\CreateWishlist;
use App\Wishlist\Application\DeleteWishlist;
use App\Wishlist\Application\DeleteWishlistItem;
use App\Wishlist\Application\Dto\AddWishlistItemRequest;
use App\Wishlist\Application\Dto\CreateWishlistRequest;
use App\Wishlist\Application\Dto\UpdateWishlistItemRequest;
use App\Wishlist\Application\Dto\UpdateWishlistRequest;
use App\Wishlist\Application\FindWishlists;
use App\Wishlist\Application\UpdateWishlist;
use App\Wishlist\Application\UpdateWishlistItem;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/api/v1/me/wishlists')]
final readonly class WishlistController
{
    #[Route('', methods: ['GET'])]
    public function list(#[CurrentUser] User $owner, FindWishlists $finder): JsonResponse
    {
        return new JsonResponse($finder->list($owner)->toArray());
    }

    #[Route('', methods: ['POST'])]
    public function create(
        #[CurrentUser] User $owner,
        #[MapRequestPayload] CreateWishlistRequest $request,
        CreateWishlist $service,
    ): JsonResponse {
        return new JsonResponse($service->create($owner, $request)->toArray(), Response::HTTP_CREATED);
    }

    #[Route('/{id}', methods: ['GET'])]
    public function get(#[CurrentUser] User $owner, string $id, FindWishlists $finder): JsonResponse
    {
        return new JsonResponse($finder->get($owner, $id)->toArray());
    }

    #[Route('/{id}', methods: ['PATCH'])]
    public function update(
        #[CurrentUser] User $owner,
        string $id,
        #[MapRequestPayload] UpdateWishlistRequest $request,
        UpdateWishlist $service,
    ): JsonResponse {
        return new JsonResponse($service->update($owner, $id, $request)->toArray());
    }

    #[Route('/{id}', methods: ['DELETE'])]
    public function delete(#[CurrentUser] User $owner, string $id, DeleteWishlist $service): JsonResponse
    {
        $service->delete($owner, $id);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/{id}/items', methods: ['POST'])]
    public function addItem(
        #[CurrentUser] User $owner,
        string $id,
        #[MapRequestPayload] AddWishlistItemRequest $request,
        AddWishlistItem $service,
    ): JsonResponse {
        return new JsonResponse($service->add($owner, $id, $request)->toArray(), Response::HTTP_CREATED);
    }

    #[Route('/{id}/items/{itemId}', methods: ['PATCH'])]
    public function updateItem(
        #[CurrentUser] User $owner,
        string $id,
        string $itemId,
        #[MapRequestPayload] UpdateWishlistItemRequest $request,
        UpdateWishlistItem $service,
    ): JsonResponse {
        return new JsonResponse($service->update($owner, $id, $itemId, $request)->toArray());
    }

    #[Route('/{id}/items/{itemId}', methods: ['DELETE'])]
    public function deleteItem(
        #[CurrentUser] User $owner,
        string $id,
        string $itemId,
        DeleteWishlistItem $service,
    ): JsonResponse {
        return new JsonResponse($service->delete($owner, $id, $itemId)->toArray());
    }
}
