<?php

declare(strict_types=1);

namespace App\Identity\Application\Security;

use App\Identity\Domain\Entity\User;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactory;

final readonly class EmailVerificationRateLimiter
{
    public function __construct(private RateLimiterFactory $limiter)
    {
    }

    public function consume(User $user, string $ipAddress): void
    {
        $key = hash('sha256', $user->getId()->toRfc4122().'|'.$ipAddress);
        if (!$this->limiter->create($key)->consume()->isAccepted()) {
            throw new TooManyRequestsHttpException(null, 'Te veel verificatieberichten aangevraagd. Probeer het later opnieuw.');
        }
    }
}
