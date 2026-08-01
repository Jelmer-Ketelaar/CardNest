<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Email;

use App\Identity\Application\Email\PasswordResetSender;
use Psr\Log\LoggerInterface;

final readonly class LoggingPasswordResetSender implements PasswordResetSender
{
    public function __construct(private LoggerInterface $logger)
    {
    }

    public function send(string $email, string $plainToken): void
    {
        $this->logger->info('Password reset delivery requested.', [
            'emailHash' => hash('sha256', mb_strtolower(trim($email))),
            'tokenHash' => hash('sha256', $plainToken),
        ]);
    }
}
