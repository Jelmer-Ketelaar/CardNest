<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260802190000 extends AbstractMigration
{
    /** @var list<string> */
    public const array CARD_LANGUAGES = [
        'en',
        'nl',
        'ja',
        'ko',
        'de',
        'fr',
        'it',
        'es',
        'es-mx',
        'pt',
        'pt-br',
        'pt-pt',
        'zh',
        'zh-tw',
        'zh-cn',
        'pl',
        'ru',
        'id',
        'th',
    ];

    /** @var list<string> */
    private const array LEGACY_WISHLIST_LANGUAGES = [
        'en',
        'nl',
        'ja',
        'ko',
        'de',
        'fr',
        'it',
        'es',
        'pt',
        'zh',
    ];

    public function getDescription(): string
    {
        return 'Align collection and wishlist language constraints with CardLanguage';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->platform instanceof PostgreSQLPlatform,
            'This migration can only be executed safely on PostgreSQL.',
        );

        $languageList = self::languageList(self::CARD_LANGUAGES);
        $this->addSql($this->auditSql('collection_items', 'language', $languageList));
        $this->addSql($this->auditSql('wishlist_items', 'desired_language', $languageList));

        $this->addSql('ALTER TABLE wishlist_items DROP CONSTRAINT chk_wishlist_language');
        $this->addSql(
            'ALTER TABLE wishlist_items ADD CONSTRAINT chk_wishlist_language '
            .'CHECK (desired_language IS NULL OR desired_language IN ('.$languageList.')) NOT VALID',
        );
        $this->addSql('ALTER TABLE wishlist_items VALIDATE CONSTRAINT chk_wishlist_language');

        $this->addSql(
            'ALTER TABLE collection_items ADD CONSTRAINT chk_collection_language '
            .'CHECK (language IN ('.$languageList.')) NOT VALID',
        );
        $this->addSql('ALTER TABLE collection_items VALIDATE CONSTRAINT chk_collection_language');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->platform instanceof PostgreSQLPlatform,
            'This migration can only be executed safely on PostgreSQL.',
        );

        $legacyLanguageList = self::languageList(self::LEGACY_WISHLIST_LANGUAGES);
        $this->addSql($this->auditSql('wishlist_items', 'desired_language', $legacyLanguageList));
        $this->addSql('ALTER TABLE collection_items DROP CONSTRAINT chk_collection_language');
        $this->addSql('ALTER TABLE wishlist_items DROP CONSTRAINT chk_wishlist_language');
        $this->addSql(
            'ALTER TABLE wishlist_items ADD CONSTRAINT chk_wishlist_language '
            .'CHECK (desired_language IS NULL OR desired_language IN ('.$legacyLanguageList.')) NOT VALID',
        );
        $this->addSql('ALTER TABLE wishlist_items VALIDATE CONSTRAINT chk_wishlist_language');
    }

    private function auditSql(string $table, string $column, string $languageList): string
    {
        return 'DO $$ DECLARE unexpected_values TEXT; BEGIN '
            ."SELECT string_agg(language_value, ', ' ORDER BY language_value) INTO unexpected_values "
            .'FROM (SELECT DISTINCT '.$column.' AS language_value FROM '.$table.' '
            .'WHERE '.$column.' IS NOT NULL AND '.$column.' NOT IN ('.$languageList.')) unexpected; '
            .'IF unexpected_values IS NOT NULL THEN '
            ."RAISE EXCEPTION USING MESSAGE = 'Cannot enforce CardLanguage persistence contract: unexpected "
            .$table.'.'.$column." value(s): ' || unexpected_values, "
            ."HINT = 'Map these values to supported CardLanguage codes before rerunning this migration.'; "
            .'END IF; END $$';
    }

    /** @param list<string> $languages */
    private static function languageList(array $languages): string
    {
        return "'".implode("', '", $languages)."'";
    }
}
