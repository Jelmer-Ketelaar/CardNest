<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Email;

use App\Identity\Application\Email\EmailVerificationSender;
use Psr\Log\LoggerInterface;

final readonly class LoggingEmailVerificationSender implements EmailVerificationSender
{
    public function __construct(private LoggerInterface $logger)
    {
    }

    public function send(string $email, string $verificationToken): void
    {
        $this->logger->info('Email verification requested.', [
            'email' => $email,
            'verificationTokenHash' => hash('sha256', $verificationToken),
        ]);
    }
}
