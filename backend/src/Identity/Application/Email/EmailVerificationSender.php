<?php

declare(strict_types=1);

namespace App\Identity\Application\Email;

interface EmailVerificationSender
{
    public function send(string $email, string $verificationToken): void;
}
