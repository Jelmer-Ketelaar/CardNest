<?php

declare(strict_types=1);

namespace App\Identity\Application\Message;

final readonly class SendVerificationEmail
{
    public function __construct(public string $email, public string $verificationToken)
    {
    }
}
