<?php

declare(strict_types=1);

namespace App\Binder\Infrastructure\Persistence;

use App\Binder\Domain\Entity\Binder;
use App\Binder\Domain\Entity\BinderSlot;
use App\Binder\Domain\Repository\BinderRepository;
use App\Identity\Domain\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;
use Throwable;

final readonly class DoctrineBinderRepository implements BinderRepository
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function findAllForOwner(User $owner): array
    {
        /** @var list<Binder> $binders */
        $binders = $this->entityManager->createQueryBuilder()
            ->select('b', 'parent')
            ->from(Binder::class, 'b')
            ->leftJoin('b.parentLocation', 'parent')
            ->where('b.owner = :owner')
            ->setParameter('owner', $owner)
            ->orderBy('b.name', 'ASC')
            ->getQuery()
            ->getResult();

        return $binders;
    }

    public function getForOwner(string $id, User $owner): ?Binder
    {
        $uuid = $this->parseUuid($id);
        if (!$uuid instanceof Uuid) {
            return null;
        }

        /** @var Binder|null $binder */
        $binder = $this->entityManager->createQueryBuilder()
            ->select('b', 'parent', 'p', 's', 'reserved')
            ->from(Binder::class, 'b')
            ->leftJoin('b.parentLocation', 'parent')
            ->leftJoin('b.pages', 'p')
            ->leftJoin('p.slots', 's')
            ->leftJoin('s.reservedCatalogCard', 'reserved')
            ->where('b.id = :id')
            ->andWhere('b.owner = :owner')
            ->setParameter('id', $uuid, UuidType::NAME)
            ->setParameter('owner', $owner)
            ->getQuery()
            ->getOneOrNullResult();

        return $binder;
    }

    public function getSlotForOwner(string $binderId, string $slotId, User $owner): ?BinderSlot
    {
        $binderUuid = $this->parseUuid($binderId);
        $slotUuid = $this->parseUuid($slotId);
        if (!$binderUuid instanceof Uuid || !$slotUuid instanceof Uuid) {
            return null;
        }

        /** @var BinderSlot|null $slot */
        $slot = $this->entityManager->createQueryBuilder()
            ->select('s', 'p', 'b', 'reserved')
            ->from(BinderSlot::class, 's')
            ->join('s.page', 'p')
            ->join('p.binder', 'b')
            ->leftJoin('s.reservedCatalogCard', 'reserved')
            ->where('s.id = :slotId')
            ->andWhere('b.id = :binderId')
            ->andWhere('b.owner = :owner')
            ->setParameter('slotId', $slotUuid, UuidType::NAME)
            ->setParameter('binderId', $binderUuid, UuidType::NAME)
            ->setParameter('owner', $owner)
            ->getQuery()
            ->getOneOrNullResult();

        return $slot;
    }

    public function save(Binder $binder): void
    {
        $this->entityManager->persist($binder);
        $this->entityManager->flush();
    }

    public function saveSlot(BinderSlot $slot): void
    {
        $this->entityManager->persist($slot);
        $this->entityManager->flush();
    }

    private function parseUuid(string $value): ?Uuid
    {
        try {
            return Uuid::fromString($value);
        } catch (Throwable) {
            return null;
        }
    }
}
