<?php

declare(strict_types=1);

namespace App\Identity\Application;

use App\Identity\Application\Dto\RegisterRequest;
use App\Identity\Application\Exception\EmailAlreadyRegistered;
use App\Identity\Application\Message\SendVerificationEmail;
use App\Identity\Domain\Entity\EmailVerificationToken;
use App\Identity\Domain\Entity\User;
use App\Identity\Domain\Repository\EmailVerificationTokenRepository;
use App\Identity\Domain\Repository\UserRepository;
use App\Shared\Domain\Transaction\TransactionManager;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final readonly class RegisterUser
{
    public function __construct(
        private UserRepository $users,
        private EmailVerificationTokenRepository $verificationTokens,
        private UserPasswordHasherInterface $passwordHasher,
        private TransactionManager $transactions,
        private MessageBusInterface $messageBus,
    ) {
    }

    public function register(RegisterRequest $request): User
    {
        if ($this->users->findByEmail($request->email) instanceof User) {
            throw new EmailAlreadyRegistered('Er bestaat al een account met dit e-mailadres.');
        }

        $passwordSubject = new User($request->email, 'pending');
        $user = new User($request->email, $this->passwordHasher->hashPassword($passwordSubject, $request->password));
        $plainToken = bin2hex(random_bytes(32));
        $this->transactions->run(function () use ($user, $plainToken): void {
            $this->users->save($user);
            $this->verificationTokens->replaceFor($user, EmailVerificationToken::issue($user, $plainToken));
        });
        $this->messageBus->dispatch(new SendVerificationEmail($user->getEmail(), $plainToken));

        return $user;
    }
}
