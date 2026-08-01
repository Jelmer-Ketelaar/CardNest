<?php

declare(strict_types=1);

namespace App\Identity\Application\Message;

use App\Identity\Application\Email\PasswordResetSender;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class SendPasswordResetEmailHandler
{
    public function __construct(private PasswordResetSender $sender)
    {
    }

    public function __invoke(SendPasswordResetEmail $message): void
    {
        $this->sender->send($message->email, $message->resetToken);
    }
}
