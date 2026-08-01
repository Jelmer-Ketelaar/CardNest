<?php

declare(strict_types=1);

namespace App\Collection\Infrastructure\Persistence;

use App\Collection\Domain\Entity\Collection;
use App\Collection\Domain\Repository\CollectionRepository;
use App\Identity\Domain\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineCollectionRepository implements CollectionRepository
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function findForOwner(User $owner): ?Collection
    {
        /** @var Collection|null $collection */
        $collection = $this->entityManager->getRepository(Collection::class)->findOneBy(['owner' => $owner]);

        return $collection;
    }

    public function getOrCreateForOwner(User $owner): Collection
    {
        $collection = $this->findForOwner($owner);
        if ($collection instanceof Collection) {
            return $collection;
        }

        $collection = new Collection($owner);
        $this->entityManager->persist($collection);

        return $collection;
    }
}
