<?php

declare(strict_types=1);

namespace App\Identity\Domain\Repository;

use App\Identity\Domain\Entity\PasswordResetToken;
use App\Identity\Domain\Entity\User;

interface PasswordResetTokenRepository
{
    public function findByPlainToken(string $plainToken): ?PasswordResetToken;

    public function replaceFor(User $user, PasswordResetToken $token): void;

    public function remove(PasswordResetToken $token): void;
}
