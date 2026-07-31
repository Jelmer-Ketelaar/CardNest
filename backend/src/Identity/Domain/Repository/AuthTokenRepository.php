<?php

declare(strict_types=1);

namespace App\Identity\Domain\Repository;

use App\Identity\Domain\Entity\AuthToken;

interface AuthTokenRepository
{
    public function findValid(string $plainToken): ?AuthToken;

    public function save(AuthToken $token): void;

    public function revoke(string $plainToken): void;
}
