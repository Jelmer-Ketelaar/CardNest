<?php

declare(strict_types=1);

namespace App\Marketplace\Presentation\Http;

use App\Identity\Domain\Entity\User;
use App\Marketplace\Application\FindListings;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/api/v1/me/listings')]
final readonly class SellerListingController
{
    #[Route('', methods: ['GET'])]
    public function list(#[CurrentUser] User $seller, FindListings $finder): JsonResponse
    {
        return new JsonResponse($finder->listForSeller($seller)->toArray());
    }

    #[Route('/{id}', methods: ['GET'])]
    public function get(#[CurrentUser] User $seller, string $id, FindListings $finder): JsonResponse
    {
        return new JsonResponse($finder->getForSeller($seller, $id)->toArray());
    }
}
