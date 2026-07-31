<?php

declare(strict_types=1);

namespace App\Identity\Application\Security;

use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactory;

final readonly class LoginRateLimiter
{
    public function __construct(private RateLimiterFactory $limiter)
    {
    }

    public function consume(string $email, string $ipAddress): void
    {
        $key = hash('sha256', mb_strtolower($email).'|'.$ipAddress);
        if (!$this->limiter->create($key)->consume()->isAccepted()) {
            throw new TooManyRequestsHttpException(null, 'Te veel inlogpogingen. Probeer het later opnieuw.');
        }
    }
}
