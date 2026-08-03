<?php

declare(strict_types=1);

namespace App\Tests\Integration\Shared;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\NullLogger;
use ReflectionClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class SchemaReconciliationMigrationContractTest extends KernelTestCase
{
    private const string MIGRATION_CLASS = 'DoctrineMigrations\\Version20260803230031';

    protected function setUp(): void
    {
        self::bootKernel();
    }

    public function testMigrationAvoidsBlockingArtistIndexRebuilds(): void
    {
        // Arrange
        $migration = $this->migration();

        // Act
        $statements = $this->plannedStatements($migration, up: true);

        // Assert
        $this->assertFalse($migration->isTransactional());
        $this->assertContains(
            'DROP INDEX CONCURRENTLY IF EXISTS idx_artists_normalized_name',
            $statements,
        );
    }

    public function testMigrationReconcilesPaymentDefaultAndStableIndexName(): void
    {
        // Arrange
        $migration = $this->migration();

        // Act
        $statements = $this->plannedStatements($migration, up: true);

        // Assert
        $this->assertContains(
            'ALTER TABLE payments ALTER finalization_status DROP DEFAULT',
            $statements,
        );
        $this->assertContains(
            'ALTER INDEX IF EXISTS idx_65d29b324ccb540a RENAME TO idx_payments_checkout_cart',
            $statements,
        );
    }

    public function testDownMigrationRestoresThePreviousSchemaContract(): void
    {
        // Arrange
        $migration = $this->migration();

        // Act
        $statements = $this->plannedStatements($migration, up: false);

        // Assert
        $this->assertContains(
            'CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_artists_normalized_name ON artists (normalized_name)',
            $statements,
        );
        $this->assertContains(
            "ALTER TABLE payments ALTER finalization_status SET DEFAULT 'not_required'",
            $statements,
        );
        $this->assertContains(
            'ALTER INDEX IF EXISTS idx_payments_checkout_cart RENAME TO idx_65d29b324ccb540a',
            $statements,
        );
    }

    /** @return list<string> */
    private function plannedStatements(AbstractMigration $migration, bool $up): array
    {
        $schema = new Schema();
        if ($up) {
            $migration->up($schema);
        } else {
            $migration->down($schema);
        }

        return array_values(array_map(
            static fn ($query): string => $query->getStatement(),
            $migration->getSql(),
        ));
    }

    private function migration(): AbstractMigration
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $migration = $this->migrationReflection()->newInstance(
            $entityManager->getConnection(),
            new NullLogger(),
        );
        $this->assertInstanceOf(AbstractMigration::class, $migration);

        return $migration;
    }

    /** @return ReflectionClass<object> */
    private function migrationReflection(): ReflectionClass
    {
        require_once dirname(__DIR__, 3).'/migrations/Version20260803230031.php';
        /** @var class-string<object> $migrationClass */
        $migrationClass = self::MIGRATION_CLASS;

        return new ReflectionClass($migrationClass);
    }
}
