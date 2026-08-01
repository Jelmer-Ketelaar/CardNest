<?php

declare(strict_types=1);

namespace App\Tests\Integration\Collection;

use App\Collection\Domain\Repository\CollectionRepository;
use App\Identity\Domain\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

final class CollectionRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private CollectionRepository $repository;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $this->entityManager = $container->get(EntityManagerInterface::class);
        $this->repository = $container->get(CollectionRepository::class);
    }

    public function testGetsOrCreatesExactlyOneDefaultCollectionForAnOwner(): void
    {
        // Arrange
        $owner = new User('owner-'.Uuid::v7()->toRfc4122().'@example.com', 'hash');
        $this->entityManager->persist($owner);
        $this->entityManager->flush();

        // Act
        $created = $this->repository->getOrCreateForOwner($owner);
        $this->entityManager->flush();
        $ownerId = $owner->getId()->toRfc4122();
        $collectionId = $created->getId()->toRfc4122();
        $this->entityManager->clear();
        $reloadedOwner = $this->entityManager->find(User::class, $ownerId);
        self::assertInstanceOf(User::class, $reloadedOwner);
        $found = $this->repository->findForOwner($reloadedOwner);
        self::assertNotNull($found);
        $again = $this->repository->getOrCreateForOwner($reloadedOwner);

        // Assert
        self::assertSame($ownerId, $found->getOwner()->getId()->toRfc4122());
        self::assertSame($collectionId, $found->getId()->toRfc4122());
        self::assertSame($collectionId, $again->getId()->toRfc4122());
    }
}
