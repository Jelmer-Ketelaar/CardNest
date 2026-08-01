<?php

declare(strict_types=1);

namespace App\Identity\Application;

use App\Identity\Application\Exception\InvalidEmailVerificationToken;
use App\Identity\Domain\Entity\EmailVerificationToken;
use App\Identity\Domain\Entity\User;
use App\Identity\Domain\Repository\EmailVerificationTokenRepository;
use App\Identity\Domain\Repository\UserRepository;
use App\Shared\Domain\Transaction\TransactionManager;
use DateTimeImmutable;

final readonly class VerifyEmail
{
    public function __construct(
        private EmailVerificationTokenRepository $tokens,
        private UserRepository $users,
        private TransactionManager $transactions,
    ) {
    }

    public function verify(string $plainToken): User
    {
        return $this->transactions->run(function () use ($plainToken): User {
            $candidate = $this->tokens->findByPlainToken($plainToken);
            if (!$candidate instanceof EmailVerificationToken) {
                throw $this->invalidToken();
            }

            $user = $this->users->lockById($candidate->getUser()->getId()->toRfc4122());
            $token = $this->tokens->findByPlainToken($plainToken);
            if (!$user instanceof User || !$token instanceof EmailVerificationToken || $token->isExpired()) {
                throw $this->invalidToken();
            }

            $user->verifyEmail(new DateTimeImmutable());
            $this->tokens->remove($token);
            $this->users->save($user);

            return $user;
        });
    }

    private function invalidToken(): InvalidEmailVerificationToken
    {
        return new InvalidEmailVerificationToken('De verificatietoken is ongeldig of verlopen.');
    }
}
