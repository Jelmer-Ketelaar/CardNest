<?php

declare(strict_types=1);

namespace App\Identity\Domain\Repository;

use App\Identity\Domain\Entity\EmailVerificationToken;
use App\Identity\Domain\Entity\User;

interface EmailVerificationTokenRepository
{
    public function findByPlainToken(string $plainToken): ?EmailVerificationToken;

    public function replaceFor(User $user, EmailVerificationToken $token): void;

    public function remove(EmailVerificationToken $token): void;
}
