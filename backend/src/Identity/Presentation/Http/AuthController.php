<?php

declare(strict_types=1);

namespace App\Identity\Presentation\Http;

use App\Identity\Application\Dto\LoginRequest;
use App\Identity\Application\Dto\RegisterRequest;
use App\Identity\Application\Dto\UserResponse;
use App\Identity\Application\LoginUser;
use App\Identity\Application\LogoutUser;
use App\Identity\Application\RegisterUser;
use App\Identity\Application\Security\LoginRateLimiter;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/v1/auth')]
final readonly class AuthController
{
    #[Route('/register', methods: ['POST'])]
    public function register(#[MapRequestPayload] RegisterRequest $request, RegisterUser $service): JsonResponse
    {
        return new JsonResponse(
            ['user' => (new UserResponse($service->register($request)))->toArray()],
            Response::HTTP_CREATED,
        );
    }

    #[Route('/login', methods: ['POST'])]
    public function login(
        #[MapRequestPayload] LoginRequest $request,
        Request $httpRequest,
        LoginRateLimiter $rateLimiter,
        LoginUser $service,
    ): JsonResponse {
        $rateLimiter->consume($request->email, $httpRequest->getClientIp() ?? 'unknown');
        $result = $service->login($request);

        return new JsonResponse([
            'user' => (new UserResponse($result['user']))->toArray(),
            'token' => $result['token'],
        ]);
    }

    #[Route('/logout', methods: ['POST'])]
    public function logout(Request $request, LogoutUser $service): Response
    {
        $service->logout(substr((string) $request->headers->get('Authorization'), 7));

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
