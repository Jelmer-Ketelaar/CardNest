<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Persistence;

use App\Identity\Domain\Entity\EmailVerificationToken;
use App\Identity\Domain\Entity\User;
use App\Identity\Domain\Repository\EmailVerificationTokenRepository;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineEmailVerificationTokenRepository implements EmailVerificationTokenRepository
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function findByPlainToken(string $plainToken): ?EmailVerificationToken
    {
        return $this->entityManager->getRepository(EmailVerificationToken::class)->findOneBy([
            'tokenHash' => EmailVerificationToken::hash($plainToken),
        ]);
    }

    public function replaceFor(User $user, EmailVerificationToken $token): void
    {
        $this->entityManager->createQueryBuilder()
            ->delete(EmailVerificationToken::class, 'token')
            ->where('token.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->execute();
        $this->entityManager->persist($token);
    }

    public function remove(EmailVerificationToken $token): void
    {
        $this->entityManager->remove($token);
    }
}
