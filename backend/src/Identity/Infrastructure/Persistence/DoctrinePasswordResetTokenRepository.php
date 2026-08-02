<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Persistence;

use App\Identity\Domain\Entity\PasswordResetToken;
use App\Identity\Domain\Entity\User;
use App\Identity\Domain\Repository\PasswordResetTokenRepository;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrinePasswordResetTokenRepository implements PasswordResetTokenRepository
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function findByPlainToken(string $plainToken): ?PasswordResetToken
    {
        return $this->entityManager->getRepository(PasswordResetToken::class)->findOneBy([
            'tokenHash' => PasswordResetToken::hash($plainToken),
        ]);
    }

    public function replaceFor(User $user, PasswordResetToken $token): void
    {
        $this->entityManager->createQueryBuilder()
            ->delete(PasswordResetToken::class, 'token')
            ->where('token.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->execute();
        $this->entityManager->persist($token);
    }

    public function remove(PasswordResetToken $token): void
    {
        $this->entityManager->remove($token);
    }
}
