<?php

declare(strict_types=1);

namespace App\Identity\Presentation\Http;

use App\Identity\Application\ConfirmPasswordReset;
use App\Identity\Application\Dto\ConfirmPasswordResetRequest;
use App\Identity\Application\Dto\LoginRequest;
use App\Identity\Application\Dto\RegisterRequest;
use App\Identity\Application\Dto\RequestPasswordResetRequest;
use App\Identity\Application\Dto\UserResponse;
use App\Identity\Application\Dto\VerifyEmailRequest;
use App\Identity\Application\LoginUser;
use App\Identity\Application\LogoutUser;
use App\Identity\Application\RegisterUser;
use App\Identity\Application\RequestPasswordReset;
use App\Identity\Application\ResendEmailVerification;
use App\Identity\Application\Security\EmailVerificationRateLimiter;
use App\Identity\Application\Security\LoginRateLimiter;
use App\Identity\Application\Security\PasswordResetRateLimiter;
use App\Identity\Application\VerifyEmail;
use App\Identity\Domain\Entity\User;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

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

    #[Route('/forgot-password', methods: ['POST'])]
    public function requestPasswordReset(
        #[MapRequestPayload] RequestPasswordResetRequest $request,
        Request $httpRequest,
        PasswordResetRateLimiter $rateLimiter,
        RequestPasswordReset $service,
    ): Response {
        $rateLimiter->consume($request->email, $httpRequest->getClientIp() ?? 'unknown');
        $service->request($request);

        return new Response(status: Response::HTTP_ACCEPTED);
    }

    #[Route('/reset-password', methods: ['POST'])]
    public function confirmPasswordReset(
        #[MapRequestPayload] ConfirmPasswordResetRequest $request,
        ConfirmPasswordReset $service,
    ): Response {
        $service->confirm($request);

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    #[Route('/email-verification/verify', methods: ['POST'])]
    public function verifyEmail(
        #[MapRequestPayload] VerifyEmailRequest $request,
        VerifyEmail $service,
    ): JsonResponse {
        return new JsonResponse([
            'user' => (new UserResponse($service->verify($request->token)))->toArray(),
        ]);
    }

    #[Route('/email-verification/resend', methods: ['POST'])]
    public function resendVerificationEmail(
        #[CurrentUser] User $user,
        Request $request,
        EmailVerificationRateLimiter $rateLimiter,
        ResendEmailVerification $service,
    ): Response {
        $rateLimiter->consume($user, $request->getClientIp() ?? 'unknown');
        $service->resend($user);

        return new Response(status: Response::HTTP_ACCEPTED);
    }
}
