<?php

declare(strict_types=1);

namespace App\Tests\Integration\Catalog;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\NullLogger;
use ReflectionClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class CatalogCoverageMigrationContractTest extends KernelTestCase
{
    private const string MIGRATION_CLASS = 'DoctrineMigrations\\Version20260804003000';

    protected function setUp(): void
    {
        self::bootKernel();
    }

    public function testMigrationCreatesAuditableDiscrepanciesAndImmutableCoverageEvidence(): void
    {
        // Act
        $sql = implode("\n", $this->plannedStatements(up: true));

        // Assert
        $this->assertStringContainsString('CREATE TABLE catalog_discrepancies', $sql);
        $this->assertStringContainsString('CREATE TABLE catalog_coverage_snapshots', $sql);
        $this->assertStringContainsString('uniq_catalog_discrepancy_fingerprint', $sql);
        $this->assertStringContainsString('uniq_catalog_coverage_manifest_snapshot', $sql);
        $this->assertStringContainsString('chk_catalog_discrepancy_resolution', $sql);
        $this->assertStringContainsString('chk_catalog_discrepancy_resolver_identifier', $sql);
        $this->assertStringContainsString('chk_catalog_coverage_release_gate_status', $sql);
        $this->assertStringContainsString('chk_catalog_coverage_approver_identifier', $sql);
        $this->assertStringContainsString('chk_catalog_coverage_counts', $sql);
        $this->assertStringContainsString('fk_catalog_discrepancy_import_run', $sql);
        $this->assertStringContainsString('fk_catalog_coverage_import_run', $sql);
        $this->assertStringContainsString('ON DELETE RESTRICT', $sql);
        $this->assertStringContainsString('ON DELETE SET NULL', $sql);
    }

    public function testDownMigrationDropsCoverageBeforeDiscrepancies(): void
    {
        // Act
        $statements = $this->plannedStatements(up: false);

        // Assert
        $this->assertSame('DROP TABLE catalog_coverage_snapshots', $statements[4]);
        $this->assertSame('DROP TABLE catalog_discrepancies', $statements[5]);
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
        $this->assertInstanceOf(AbstractMigration::class, $migration);

        return $migration;
    }

    /** @return ReflectionClass<object> */
    private function migrationReflection(): ReflectionClass
    {
        require_once dirname(__DIR__, 3).'/migrations/Version20260804003000.php';
        /** @var class-string<object> $migrationClass */
        $migrationClass = self::MIGRATION_CLASS;

        return new ReflectionClass($migrationClass);
    }
}
