<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260804010000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add canonical Pokémon species identities, aliases, and source references';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->platform instanceof PostgreSQLPlatform,
            'This migration can only be executed safely on PostgreSQL.',
        );

        $this->addSql(<<<'SQL'
            CREATE TABLE pokemon_species (
                id UUID NOT NULL,
                national_dex_number INT NOT NULL,
                canonical_name VARCHAR(180) NOT NULL,
                normalized_name VARCHAR(180) NOT NULL,
                slug VARCHAR(220) NOT NULL,
                generation INT NOT NULL,
                introduced_at DATE DEFAULT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY (id)
            )
            SQL);
        $this->addSql(
            'CREATE UNIQUE INDEX uniq_pokemon_species_national_dex ON pokemon_species (national_dex_number)',
        );
        $this->addSql(
            'CREATE UNIQUE INDEX uniq_pokemon_species_normalized_name ON pokemon_species (normalized_name)',
        );
        $this->addSql('CREATE UNIQUE INDEX uniq_pokemon_species_slug ON pokemon_species (slug)');
        $this->addSql(<<<'SQL'
            ALTER TABLE pokemon_species
            ADD CONSTRAINT chk_pokemon_species_identity
            CHECK (
                national_dex_number > 0
                AND generation > 0
                AND btrim(canonical_name) <> ''
                AND btrim(normalized_name) <> ''
                AND btrim(slug) <> ''
                AND updated_at >= created_at
            )
            SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE pokemon_species_aliases (
                id UUID NOT NULL,
                species_id UUID NOT NULL,
                canonical_name VARCHAR(180) NOT NULL,
                normalized_name VARCHAR(180) NOT NULL,
                alias_type VARCHAR(30) NOT NULL,
                language VARCHAR(10) DEFAULT NULL,
                source VARCHAR(100) NOT NULL,
                source_reference VARCHAR(500) DEFAULT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY (id)
            )
            SQL);
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX uniq_pokemon_species_alias_normalized
            ON pokemon_species_aliases (species_id, normalized_name)
            SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX idx_pokemon_species_alias_normalized
            ON pokemon_species_aliases (normalized_name)
            SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE pokemon_species_aliases
            ADD CONSTRAINT chk_pokemon_species_alias_identity
            CHECK (
                btrim(canonical_name) <> ''
                AND btrim(normalized_name) <> ''
                AND btrim(source) <> ''
                AND (source_reference IS NULL OR btrim(source_reference) <> '')
            )
            SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE pokemon_species_aliases
            ADD CONSTRAINT chk_pokemon_species_alias_type
            CHECK (alias_type IN (
                'localized_name',
                'alternate_spelling',
                'transliteration',
                'former_official_name'
            ))
            SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE pokemon_species_aliases
            ADD CONSTRAINT chk_pokemon_species_alias_language
            CHECK (
                language IS NULL
                OR language ~ '^[a-z]{2,3}(-[a-z0-9]{2,8})*$'
            )
            SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE pokemon_species_aliases
            ADD CONSTRAINT chk_pokemon_species_alias_source
            CHECK (source ~ '^[a-z0-9][a-z0-9._-]{0,99}$')
            SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE pokemon_species_aliases
            ADD CONSTRAINT fk_pokemon_species_alias_species
            FOREIGN KEY (species_id)
            REFERENCES pokemon_species (id)
            ON DELETE CASCADE
            NOT DEFERRABLE
            SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE pokemon_species_external_references (
                id UUID NOT NULL,
                species_id UUID NOT NULL,
                provider VARCHAR(100) NOT NULL,
                external_id VARCHAR(180) NOT NULL,
                source_version VARCHAR(180) DEFAULT NULL,
                imported_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                last_synced_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY (id)
            )
            SQL);
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX uniq_pokemon_species_external_reference
            ON pokemon_species_external_references (provider, external_id)
            SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX idx_pokemon_species_external_species
            ON pokemon_species_external_references (species_id)
            SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE pokemon_species_external_references
            ADD CONSTRAINT chk_pokemon_species_external_identity
            CHECK (
                provider ~ '^[a-z0-9][a-z0-9._-]{0,99}$'
                AND btrim(external_id) <> ''
                AND (source_version IS NULL OR btrim(source_version) <> '')
            )
            SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE pokemon_species_external_references
            ADD CONSTRAINT chk_pokemon_species_external_sync
            CHECK (last_synced_at >= imported_at AND updated_at >= created_at)
            SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE pokemon_species_external_references
            ADD CONSTRAINT fk_pokemon_species_external_species
            FOREIGN KEY (species_id)
            REFERENCES pokemon_species (id)
            ON DELETE CASCADE
            NOT DEFERRABLE
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->platform instanceof PostgreSQLPlatform,
            'This migration can only be executed safely on PostgreSQL.',
        );

        $this->addSql('DROP TABLE pokemon_species_external_references');
        $this->addSql('DROP TABLE pokemon_species_aliases');
        $this->addSql('DROP TABLE pokemon_species');
    }
}
