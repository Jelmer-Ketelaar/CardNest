<?php

declare(strict_types=1);

namespace App\Identity\Presentation\Http;

use App\Identity\Application\Dto\UserResponse;
use App\Identity\Domain\Entity\User;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/api/v1/me')]
final readonly class CurrentUserController
{
    #[Route('', methods: ['GET'])]
    public function get(#[CurrentUser] User $user): JsonResponse
    {
        return new JsonResponse(['user' => (new UserResponse($user))->toArray()]);
    }
}
