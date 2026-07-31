<?php

declare(strict_types=1);

namespace App\Identity\Application;

use App\Identity\Domain\Repository\AuthTokenRepository;

final readonly class LogoutUser
{
    public function __construct(private AuthTokenRepository $tokens)
    {
    }

    public function logout(string $plainToken): void
    {
        $this->tokens->revoke($plainToken);
    }
}
