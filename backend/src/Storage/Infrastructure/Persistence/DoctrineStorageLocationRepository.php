<?php

declare(strict_types=1);

namespace App\Storage\Infrastructure\Persistence;

use App\Identity\Domain\Entity\User;
use App\Storage\Domain\Entity\StorageLocation;
use App\Storage\Domain\Repository\StorageLocationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;
use Throwable;

final readonly class DoctrineStorageLocationRepository implements StorageLocationRepository
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function findAllForOwner(User $owner): array
    {
        /** @var list<StorageLocation> $locations */
        $locations = $this->entityManager->createQueryBuilder()
            ->select('l', 'p')
            ->from(StorageLocation::class, 'l')
            ->leftJoin('l.parent', 'p')
            ->where('l.owner = :owner')
            ->setParameter('owner', $owner)
            ->orderBy('l.name', 'ASC')
            ->getQuery()
            ->getResult();

        return $locations;
    }

    public function getForOwner(string $id, User $owner): ?StorageLocation
    {
        try {
            $uuid = Uuid::fromString($id);
        } catch (Throwable) {
            return null;
        }

        /** @var StorageLocation|null $location */
        $location = $this->entityManager->createQueryBuilder()
            ->select('l', 'p')
            ->from(StorageLocation::class, 'l')
            ->leftJoin('l.parent', 'p')
            ->where('l.id = :id')
            ->andWhere('l.owner = :owner')
            ->setParameter('id', $uuid, UuidType::NAME)
            ->setParameter('owner', $owner)
            ->getQuery()
            ->getOneOrNullResult();

        return $location;
    }

    public function save(StorageLocation $location): void
    {
        $this->entityManager->persist($location);
        $this->entityManager->flush();
    }
}
