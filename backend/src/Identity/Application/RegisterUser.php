<?php

declare(strict_types=1);

namespace App\Identity\Application;

use App\Identity\Application\Dto\RegisterRequest;
use App\Identity\Application\Exception\EmailAlreadyRegistered;
use App\Identity\Application\Message\SendVerificationEmail;
use App\Identity\Domain\Entity\User;
use App\Identity\Domain\Repository\UserRepository;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final readonly class RegisterUser
{
    public function __construct(
        private UserRepository $users,
        private UserPasswordHasherInterface $passwordHasher,
        private MessageBusInterface $messageBus,
    ) {
    }

    public function register(RegisterRequest $request): User
    {
        if ($this->users->findByEmail($request->email) instanceof User) {
            throw new EmailAlreadyRegistered('Er bestaat al een account met dit e-mailadres.');
        }

        $user = new User($request->email, 'pending');
        $user = new User($request->email, $this->passwordHasher->hashPassword($user, $request->password));
        $this->users->save($user);
        $this->messageBus->dispatch(new SendVerificationEmail($user->getEmail(), bin2hex(random_bytes(32))));

        return $user;
    }
}
