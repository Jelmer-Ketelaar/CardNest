<?php

declare(strict_types=1);

namespace App\Identity\Application;

use App\Identity\Application\Dto\LoginRequest;
use App\Identity\Application\Exception\InvalidCredentials;
use App\Identity\Domain\Entity\AuthToken;
use App\Identity\Domain\Entity\User;
use App\Identity\Domain\Repository\AuthTokenRepository;
use App\Identity\Domain\Repository\UserRepository;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final readonly class LoginUser
{
    public function __construct(
        private UserRepository $users,
        private AuthTokenRepository $tokens,
        private UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    /** @return array{user: User, token: string} */
    public function login(LoginRequest $request): array
    {
        $user = $this->users->findByEmail($request->email);
        if (!$user instanceof User || !$this->passwordHasher->isPasswordValid($user, $request->password)) {
            throw new InvalidCredentials('Ongeldig e-mailadres of wachtwoord.');
        }

        $plainToken = bin2hex(random_bytes(32));
        $this->tokens->save(AuthToken::issue($user, $plainToken));

        return ['user' => $user, 'token' => $plainToken];
    }
}
