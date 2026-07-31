<?php

declare(strict_types=1);

namespace App\Tests\Integration\Catalog;

use App\Catalog\Infrastructure\Persistence\DoctrineCardSetRepository;
use App\Catalog\Infrastructure\Persistence\DoctrineCatalogCardRepository;
use App\Catalog\Infrastructure\Persistence\Fixtures\CatalogFixtures;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class CatalogFixturesTest extends KernelTestCase
{
    public function testFixtureProvidesAtLeastTwoSetsAndTwentyCards(): void
    {
        // Arrange
        self::bootKernel();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $fixture = new CatalogFixtures(
            $entityManager,
            new DoctrineCardSetRepository($entityManager),
            new DoctrineCatalogCardRepository($entityManager),
        );

        // Act
        $fixture->load($entityManager);
        $setCount = (int) $entityManager->getConnection()->fetchOne('SELECT COUNT(*) FROM card_sets');
        $cardCount = (int) $entityManager->getConnection()->fetchOne('SELECT COUNT(*) FROM catalog_cards');

        // Assert
        self::assertGreaterThanOrEqual(2, $setCount);
        self::assertGreaterThanOrEqual(20, $cardCount);
    }
}
