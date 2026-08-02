<?php

declare(strict_types=1);

namespace App\Identity\Application;

use App\Identity\Application\Dto\ConfirmPasswordResetRequest;
use App\Identity\Application\Exception\InvalidPasswordResetToken;
use App\Identity\Domain\Entity\PasswordResetToken;
use App\Identity\Domain\Entity\User;
use App\Identity\Domain\Repository\AuthTokenRepository;
use App\Identity\Domain\Repository\PasswordResetTokenRepository;
use App\Identity\Domain\Repository\UserRepository;
use App\Shared\Domain\Transaction\TransactionManager;
use DateTimeImmutable;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final readonly class ConfirmPasswordReset
{
    public function __construct(
        private PasswordResetTokenRepository $tokens,
        private UserRepository $users,
        private AuthTokenRepository $authTokens,
        private UserPasswordHasherInterface $passwordHasher,
        private TransactionManager $transactions,
    ) {
    }

    public function confirm(ConfirmPasswordResetRequest $request): void
    {
        $this->transactions->run(function () use ($request): void {
            $candidate = $this->tokens->findByPlainToken($request->token);
            if (!$candidate instanceof PasswordResetToken) {
                throw $this->invalidToken();
            }

            $user = $this->users->lockById($candidate->getUser()->getId()->toRfc4122());
            $token = $this->tokens->findByPlainToken($request->token);
            if (!$user instanceof User || !$token instanceof PasswordResetToken || $token->isExpired()) {
                throw $this->invalidToken();
            }

            $user->changePasswordHash(
                $this->passwordHasher->hashPassword($user, $request->password),
                new DateTimeImmutable(),
            );
            $this->tokens->remove($token);
            $this->authTokens->revokeAllFor($user);
            $this->users->save($user);
        });
    }

    private function invalidToken(): InvalidPasswordResetToken
    {
        return new InvalidPasswordResetToken('De herstelcode is ongeldig of verlopen.');
    }
}
