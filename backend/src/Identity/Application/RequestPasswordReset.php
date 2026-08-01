<?php

declare(strict_types=1);

namespace App\Identity\Application;

use App\Identity\Application\Dto\RequestPasswordResetRequest;
use App\Identity\Application\Message\SendPasswordResetEmail;
use App\Identity\Domain\Entity\PasswordResetToken;
use App\Identity\Domain\Entity\User;
use App\Identity\Domain\Repository\PasswordResetTokenRepository;
use App\Identity\Domain\Repository\UserRepository;
use App\Shared\Domain\Transaction\TransactionManager;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class RequestPasswordReset
{
    public function __construct(
        private UserRepository $users,
        private PasswordResetTokenRepository $tokens,
        private TransactionManager $transactions,
        private MessageBusInterface $messageBus,
    ) {
    }

    public function request(RequestPasswordResetRequest $request): void
    {
        $plainToken = bin2hex(random_bytes(32));
        $email = $this->transactions->run(function () use ($request, $plainToken): ?string {
            $candidate = $this->users->findByEmail($request->email);
            if (!$candidate instanceof User) {
                return null;
            }

            $user = $this->users->lockById($candidate->getId()->toRfc4122());
            if (!$user instanceof User) {
                return null;
            }

            $this->tokens->replaceFor($user, PasswordResetToken::issue($user, $plainToken));

            return $user->getEmail();
        });

        if (!is_string($email)) {
            return;
        }

        $this->messageBus->dispatch(new SendPasswordResetEmail($email, $plainToken));
    }
}
