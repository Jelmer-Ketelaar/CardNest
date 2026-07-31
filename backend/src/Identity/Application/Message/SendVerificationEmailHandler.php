<?php

declare(strict_types=1);

namespace App\Identity\Application\Message;

use App\Identity\Application\Email\EmailVerificationSender;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class SendVerificationEmailHandler
{
    public function __construct(private EmailVerificationSender $sender)
    {
    }

    public function __invoke(SendVerificationEmail $message): void
    {
        $this->sender->send($message->email, $message->verificationToken);
    }
}
