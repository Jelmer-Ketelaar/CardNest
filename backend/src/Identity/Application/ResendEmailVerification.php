<?php

declare(strict_types=1);

namespace App\Identity\Application;

use App\Identity\Application\Exception\EmailAlreadyVerified;
use App\Identity\Application\Message\SendVerificationEmail;
use App\Identity\Domain\Entity\EmailVerificationToken;
use App\Identity\Domain\Entity\User;
use App\Identity\Domain\Repository\EmailVerificationTokenRepository;
use App\Identity\Domain\Repository\UserRepository;
use App\Shared\Domain\Transaction\TransactionManager;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class ResendEmailVerification
{
    public function __construct(
        private UserRepository $users,
        private EmailVerificationTokenRepository $tokens,
        private TransactionManager $transactions,
        private MessageBusInterface $messageBus,
    ) {
    }

    public function resend(User $currentUser): void
    {
        $plainToken = bin2hex(random_bytes(32));
        $email = $this->transactions->run(function () use ($currentUser, $plainToken): string {
            $user = $this->users->lockById($currentUser->getId()->toRfc4122());
            if (!$user instanceof User) {
                throw new EmailAlreadyVerified('Dit e-mailadres kan niet meer worden bevestigd.');
            }
            if ($user->isEmailVerified()) {
                throw new EmailAlreadyVerified('Dit e-mailadres is al bevestigd.');
            }

            $this->tokens->replaceFor($user, EmailVerificationToken::issue($user, $plainToken));

            return $user->getEmail();
        });

        $this->messageBus->dispatch(new SendVerificationEmail($email, $plainToken));
    }
}
