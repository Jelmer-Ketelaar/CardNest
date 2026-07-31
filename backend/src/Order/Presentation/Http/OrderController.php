<?php

declare(strict_types=1);

namespace App\Order\Presentation\Http;

use App\Identity\Domain\Entity\User;
use App\Order\Application\FindOrders;
use Symfony\Component\HttpFoundation\JsonResponse;
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
}
