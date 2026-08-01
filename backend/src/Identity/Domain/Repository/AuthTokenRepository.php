<?php

declare(strict_types=1);

namespace App\Identity\Domain\Repository;

use App\Identity\Domain\Entity\AuthToken;
use App\Identity\Domain\Entity\User;

interface AuthTokenRepository
{
    public function findValid(string $plainToken): ?AuthToken;

    public function save(AuthToken $token): void;

    public function revoke(string $plainToken): void;

    public function revokeAllFor(User $user): void;
}
