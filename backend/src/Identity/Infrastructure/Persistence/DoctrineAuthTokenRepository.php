<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Persistence;

use App\Identity\Domain\Entity\AuthToken;
use App\Identity\Domain\Entity\User;
use App\Identity\Domain\Repository\AuthTokenRepository;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineAuthTokenRepository implements AuthTokenRepository
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function findValid(string $plainToken): ?AuthToken
    {
        $token = $this->entityManager->getRepository(AuthToken::class)
            ->findOneBy(['tokenHash' => hash('sha256', $plainToken)]);

        return $token instanceof AuthToken && !$token->isExpired() ? $token : null;
    }

    public function save(AuthToken $token): void
    {
        $this->entityManager->persist($token);
        $this->entityManager->flush();
    }

    public function revoke(string $plainToken): void
    {
        $token = $this->entityManager->getRepository(AuthToken::class)
            ->findOneBy(['tokenHash' => hash('sha256', $plainToken)]);
        if ($token instanceof AuthToken) {
            $this->entityManager->remove($token);
            $this->entityManager->flush();
        }
    }

    public function revokeAllFor(User $user): void
    {
        $this->entityManager->createQueryBuilder()
            ->delete(AuthToken::class, 'token')
            ->where('token.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->execute();
    }
}
