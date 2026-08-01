<?php

declare(strict_types=1);

namespace App\Identity\Application\Email;

interface PasswordResetSender
{
    public function send(string $email, string $plainToken): void;
}
