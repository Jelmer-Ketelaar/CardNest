<?php

declare(strict_types=1);

namespace App\Tests\Integration\Collection;

use App\Collection\Domain\Entity\CardLanguage;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\NullLogger;
use ReflectionClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class CardLanguageMigrationContractTest extends KernelTestCase
{
    private const string MIGRATION_CLASS = 'DoctrineMigrations\\Version20260802190000';

    protected function setUp(): void
    {
        self::bootKernel();
    }

    public function testMigrationLanguageSetMatchesTheDomainEnum(): void
    {
        // Act
        $languages = $this->migrationReflection()->getConstant('CARD_LANGUAGES');

        // Assert
        self::assertSame(CardLanguage::values(), $languages);
    }

    public function testUpAuditsExistingValuesAndConstrainsBothPersistenceColumns(): void
    {
        // Act
        $statements = $this->plannedStatements(up: true);
        $sql = implode("\n", $statements);

        // Assert
        self::assertStringContainsString('unexpected collection_items.language value(s)', $statements[0]);
        self::assertStringContainsString('unexpected wishlist_items.desired_language value(s)', $statements[1]);
        self::assertStringContainsString('ADD CONSTRAINT chk_collection_language', $sql);
        self::assertStringContainsString('VALIDATE CONSTRAINT chk_collection_language', $sql);
        self::assertStringContainsString('ADD CONSTRAINT chk_wishlist_language', $sql);
        self::assertStringContainsString('VALIDATE CONSTRAINT chk_wishlist_language', $sql);

        foreach (CardLanguage::values() as $language) {
            self::assertStringContainsString("'".$language."'", $statements[3]);
            self::assertStringContainsString("'".$language."'", $statements[5]);
        }
    }

    public function testDownGuardsLegacyWishlistCompatibilityBeforeRelaxingCollectionConstraint(): void
    {
        // Act
        $statements = $this->plannedStatements(up: false);

        // Assert
        self::assertStringContainsString('unexpected wishlist_items.desired_language value(s)', $statements[0]);
        self::assertStringContainsString('DROP CONSTRAINT chk_collection_language', $statements[1]);
        self::assertStringContainsString('DROP CONSTRAINT chk_wishlist_language', $statements[2]);
        self::assertStringContainsString('ADD CONSTRAINT chk_wishlist_language', $statements[3]);
        self::assertStringContainsString('VALIDATE CONSTRAINT chk_wishlist_language', $statements[4]);
        self::assertStringNotContainsString("'es-mx'", $statements[3]);
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
        require_once dirname(__DIR__, 3).'/migrations/Version20260802190000.php';
        /** @var class-string<object> $migrationClass */
        $migrationClass = self::MIGRATION_CLASS;

        return new ReflectionClass($migrationClass);
    }
}
