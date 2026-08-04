<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260804020000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add official Pokémon forms and bounded source references';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->platform instanceof PostgreSQLPlatform,
            'This migration can only be executed safely on PostgreSQL.',
        );

        $this->addSql(<<<'SQL'
            CREATE TABLE pokemon_forms (
                id UUID NOT NULL,
                species_id UUID NOT NULL,
                canonical_name VARCHAR(180) NOT NULL,
                normalized_name VARCHAR(180) NOT NULL,
                slug VARCHAR(220) NOT NULL,
                form_type VARCHAR(30) NOT NULL,
                is_default BOOLEAN NOT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY (id)
            )
            SQL);
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX uniq_pokemon_form_normalized_name
            ON pokemon_forms (species_id, normalized_name)
            SQL);
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX uniq_pokemon_form_slug
            ON pokemon_forms (species_id, slug)
            SQL);
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX uniq_pokemon_form_default_per_species
            ON pokemon_forms (species_id)
            WHERE is_default = true
            SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE pokemon_forms
            ADD CONSTRAINT chk_pokemon_form_identity
            CHECK (
                btrim(canonical_name) <> ''
                AND btrim(normalized_name) <> ''
                AND btrim(slug) <> ''
                AND updated_at >= created_at
            )
            SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE pokemon_forms
            ADD CONSTRAINT chk_pokemon_form_type
            CHECK (form_type IN (
                'default',
                'regional',
                'mega_evolution',
                'primal_reversion',
                'gigantamax',
                'official_alternate'
            ))
            SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE pokemon_forms
            ADD CONSTRAINT chk_pokemon_form_default_type
            CHECK (is_default = (form_type = 'default'))
            SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE pokemon_forms
            ADD CONSTRAINT fk_pokemon_form_species
            FOREIGN KEY (species_id)
            REFERENCES pokemon_species (id)
            ON DELETE CASCADE
            NOT DEFERRABLE
            SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE pokemon_form_external_references (
                id UUID NOT NULL,
                form_id UUID NOT NULL,
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
            CREATE UNIQUE INDEX uniq_pokemon_form_external_reference
            ON pokemon_form_external_references (provider, external_id)
            SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX idx_pokemon_form_external_form
            ON pokemon_form_external_references (form_id)
            SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE pokemon_form_external_references
            ADD CONSTRAINT chk_pokemon_form_external_identity
            CHECK (
                provider ~ '^[a-z0-9][a-z0-9._-]{0,99}$'
                AND btrim(external_id) <> ''
                AND (source_version IS NULL OR btrim(source_version) <> '')
            )
            SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE pokemon_form_external_references
            ADD CONSTRAINT chk_pokemon_form_external_sync
            CHECK (last_synced_at >= imported_at AND updated_at >= created_at)
            SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE pokemon_form_external_references
            ADD CONSTRAINT fk_pokemon_form_external_form
            FOREIGN KEY (form_id)
            REFERENCES pokemon_forms (id)
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

        $this->addSql('DROP TABLE pokemon_form_external_references');
        $this->addSql('DROP TABLE pokemon_forms');
    }
}
