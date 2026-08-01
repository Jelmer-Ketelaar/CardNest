<?php

declare(strict_types=1);

namespace App\Storage\Infrastructure\Persistence;

use App\Identity\Domain\Entity\User;
use App\Storage\Domain\Entity\StorageLocation;
use App\Storage\Domain\Exception\StorageLocationInUse;
use App\Storage\Domain\Repository\StorageLocationRepository;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\DBAL\ParameterType;
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

    public function isRemovable(StorageLocation $location): bool
    {
        $isRemovable = $this->entityManager->getConnection()->fetchOne(
            <<<'SQL'
                SELECT CASE WHEN NOT EXISTS (
                    SELECT 1 FROM storage_locations child WHERE child.parent_id = :id
                ) AND NOT EXISTS (
                    SELECT 1 FROM binders binder WHERE binder.parent_location_id = :id
                ) AND NOT EXISTS (
                    SELECT 1 FROM collection_item_location_history history WHERE history.storage_location_id = :id
                ) THEN 1 ELSE 0 END
                SQL,
            ['id' => $location->getId()->toRfc4122()],
            ['id' => ParameterType::STRING],
        );

        return (int) $isRemovable === 1;
    }

    public function remove(StorageLocation $location): void
    {
        try {
            $this->entityManager->getConnection()->executeStatement(
                'DELETE FROM storage_locations WHERE id = :id',
                ['id' => $location->getId()->toRfc4122()],
                ['id' => ParameterType::STRING],
            );
        } catch (ForeignKeyConstraintViolationException) {
            throw new StorageLocationInUse('Een opslaglocatie met sublocaties, binders of locatiehistorie kan niet worden verwijderd.');
        }

        $this->entityManager->detach($location);
    }
}
