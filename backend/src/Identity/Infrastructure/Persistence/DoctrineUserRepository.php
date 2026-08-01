<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Persistence;

use App\Identity\Domain\Entity\User;
use App\Identity\Domain\Repository\UserRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;
use Throwable;

final readonly class DoctrineUserRepository implements UserRepository
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function findById(string $id): ?User
    {
        try {
            $uuid = Uuid::fromString($id);
        } catch (Throwable) {
            return null;
        }

        /** @var User|null $user */
        $user = $this->entityManager->createQueryBuilder()
            ->select('user')
            ->from(User::class, 'user')
            ->where('user.id = :id')
            ->setParameter('id', $uuid, UuidType::NAME)
            ->getQuery()
            ->getOneOrNullResult();

        return $user;
    }

    public function findByEmail(string $email): ?User
    {
        return $this->entityManager->getRepository(User::class)->findOneBy(['email' => mb_strtolower(trim($email))]);
    }

    public function lockById(string $id): ?User
    {
        try {
            $uuid = Uuid::fromString($id);
        } catch (Throwable) {
            return null;
        }

        /** @var User|null $user */
        $user = $this->entityManager->createQueryBuilder()
            ->select('user')
            ->from(User::class, 'user')
            ->where('user.id = :id')
            ->setParameter('id', $uuid, UuidType::NAME)
            ->getQuery()
            ->setLockMode(LockMode::PESSIMISTIC_WRITE)
            ->getOneOrNullResult();

        return $user;
    }

    public function save(User $user): void
    {
        $this->entityManager->persist($user);
        $this->entityManager->flush();
    }
}
