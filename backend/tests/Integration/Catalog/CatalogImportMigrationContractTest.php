<?php

declare(strict_types=1);

namespace App\Tests\Integration\Catalog;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\NullLogger;
use ReflectionClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class CatalogImportMigrationContractTest extends KernelTestCase
{
    private const string MIGRATION_CLASS = 'DoctrineMigrations\\Version20260803234442';

    protected function setUp(): void
    {
        self::bootKernel();
    }

    public function testMigrationCreatesAuditableRunsAndPersistentBoundedStaging(): void
    {
        // Act
        $statements = $this->plannedStatements(up: true);
        $sql = implode("\n", $statements);

        // Assert
        self::assertStringContainsString('CREATE TABLE catalog_import_runs', $sql);
        self::assertStringContainsString('CREATE TABLE catalog_import_staged_sets', $sql);
        self::assertStringContainsString('CREATE TABLE catalog_import_staged_cards', $sql);
        self::assertStringContainsString('chk_catalog_import_run_terminal', $sql);
        self::assertStringContainsString('chk_catalog_import_run_published_counts', $sql);
        self::assertStringContainsString('chk_catalog_import_run_completion_counts', $sql);
        self::assertStringContainsString('chk_catalog_import_staged_set_payload', $sql);
        self::assertStringContainsString('chk_catalog_import_staged_card_payload', $sql);
        self::assertStringContainsString('uniq_catalog_import_staged_set_position', $sql);
        self::assertStringContainsString('uniq_catalog_import_staged_card_position', $sql);
        self::assertStringContainsString('ON DELETE RESTRICT', $sql);
        self::assertStringContainsString('ON DELETE CASCADE', $sql);
    }

    public function testDownMigrationDropsStagingBeforeImportRuns(): void
    {
        // Act
        $statements = $this->plannedStatements(up: false);

        // Assert
        self::assertSame('DROP TABLE catalog_import_staged_cards', $statements[3]);
        self::assertSame('DROP TABLE catalog_import_staged_sets', $statements[4]);
        self::assertSame('DROP TABLE catalog_import_runs', $statements[5]);
    }

    /** @return list<string> */
    private function plannedStatements(bool $up): array
    {
        $migration = $this->migration();
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
        self::assertInstanceOf(AbstractMigration::class, $migration);

        return $migration;
    }

    /** @return ReflectionClass<object> */
    private function migrationReflection(): ReflectionClass
    {
        require_once dirname(__DIR__, 3).'/migrations/Version20260803234442.php';
        /** @var class-string<object> $migrationClass */
        $migrationClass = self::MIGRATION_CLASS;

        return new ReflectionClass($migrationClass);
    }
}
