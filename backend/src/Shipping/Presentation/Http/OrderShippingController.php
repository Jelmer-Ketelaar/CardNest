<?php

declare(strict_types=1);

namespace App\Shipping\Presentation\Http;

use App\Identity\Domain\Entity\User;
use App\Shipping\Application\ConfirmOrderReceived;
use App\Shipping\Application\MarkOrderShipped;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/api/v1/me/orders')]
final readonly class OrderShippingController
{
    #[Route('/{id}/mark-shipped', methods: ['POST'])]
    public function markShipped(#[CurrentUser] User $seller, string $id, MarkOrderShipped $service): JsonResponse
    {
        return new JsonResponse($service->mark($seller, $id)->toArray());
    }

    #[Route('/{id}/confirm-received', methods: ['POST'])]
    public function confirmReceived(#[CurrentUser] User $buyer, string $id, ConfirmOrderReceived $service): JsonResponse
    {
        return new JsonResponse($service->confirm($buyer, $id)->toArray());
    }
}
