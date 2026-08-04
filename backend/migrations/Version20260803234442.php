<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260803234442 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add auditable catalog import runs and persistent normalized staging tables';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->platform instanceof PostgreSQLPlatform,
            'This migration can only be executed safely on PostgreSQL.',
        );

        $this->addSql(<<<'SQL'
            CREATE TABLE catalog_import_runs (
                id UUID NOT NULL,
                status VARCHAR(30) NOT NULL,
                content_checksum VARCHAR(64) DEFAULT NULL,
                staged_set_count INT NOT NULL,
                staged_card_count INT NOT NULL,
                published_set_count INT NOT NULL,
                published_card_count INT NOT NULL,
                warning_count INT NOT NULL,
                error_count INT NOT NULL,
                failure_reason TEXT DEFAULT NULL,
                completed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                provider VARCHAR(100) NOT NULL,
                scope_key VARCHAR(64) NOT NULL,
                data_scope JSONB NOT NULL,
                source_version VARCHAR(180) DEFAULT NULL,
                data_cutoff TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                started_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                duplicate_of_id UUID DEFAULT NULL,
                PRIMARY KEY (id)
            )
            SQL);
        $this->addSql(
            'CREATE INDEX idx_catalog_import_runs_duplicate ON catalog_import_runs (duplicate_of_id)',
        );
        $this->addSql(<<<'SQL'
            CREATE INDEX idx_catalog_import_runs_scope_started
            ON catalog_import_runs (provider, scope_key, started_at)
            SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX idx_catalog_import_runs_source_version
            ON catalog_import_runs (provider, scope_key, source_version)
            SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX idx_catalog_import_runs_status_started
            ON catalog_import_runs (status, started_at)
            SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE catalog_import_runs
            ADD CONSTRAINT chk_catalog_import_run_status
            CHECK (status IN (
                'running',
                'validating',
                'publishing',
                'completed',
                'completed_with_warnings',
                'skipped',
                'failed'
            ))
            SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE catalog_import_runs
            ADD CONSTRAINT chk_catalog_import_run_counts
            CHECK (
                staged_set_count >= 0
                AND staged_card_count >= 0
                AND published_set_count >= 0
                AND published_card_count >= 0
                AND warning_count >= 0
                AND error_count >= 0
            )
            SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE catalog_import_runs
            ADD CONSTRAINT chk_catalog_import_run_published_counts
            CHECK (
                published_set_count <= staged_set_count
                AND published_card_count <= staged_card_count
            )
            SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE catalog_import_runs
            ADD CONSTRAINT chk_catalog_import_run_completion_counts
            CHECK (
                status NOT IN ('completed', 'completed_with_warnings')
                OR (
                    published_set_count = staged_set_count
                    AND published_card_count = staged_card_count
                )
            )
            SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE catalog_import_runs
            ADD CONSTRAINT chk_catalog_import_run_scope_key
            CHECK (scope_key ~ '^[0-9a-f]{64}$')
            SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE catalog_import_runs
            ADD CONSTRAINT chk_catalog_import_run_checksum
            CHECK (content_checksum IS NULL OR content_checksum ~ '^[0-9a-f]{64}$')
            SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE catalog_import_runs
            ADD CONSTRAINT chk_catalog_import_run_data_scope
            CHECK (jsonb_typeof(data_scope) = 'object')
            SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE catalog_import_runs
            ADD CONSTRAINT chk_catalog_import_run_terminal
            CHECK (
                (
                    status IN ('completed', 'completed_with_warnings', 'skipped', 'failed')
                    AND completed_at IS NOT NULL
                )
                OR (
                    status IN ('running', 'validating', 'publishing')
                    AND completed_at IS NULL
                )
            )
            SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE catalog_import_runs
            ADD CONSTRAINT chk_catalog_import_run_failure
            CHECK (
                (status = 'failed' AND failure_reason IS NOT NULL AND error_count > 0)
                OR (status <> 'failed' AND failure_reason IS NULL)
            )
            SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE catalog_import_runs
            ADD CONSTRAINT chk_catalog_import_run_duplicate
            CHECK (
                (
                    status = 'skipped'
                    AND duplicate_of_id IS NOT NULL
                    AND source_version IS NOT NULL
                )
                OR (status <> 'skipped' AND duplicate_of_id IS NULL)
            )
            SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE catalog_import_runs
            ADD CONSTRAINT FK_7CA2B4312CC33300
            FOREIGN KEY (duplicate_of_id)
            REFERENCES catalog_import_runs (id)
            ON DELETE RESTRICT
            NOT DEFERRABLE
            SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE catalog_import_staged_sets (
                id UUID NOT NULL,
                import_run_id UUID NOT NULL,
                position INT NOT NULL,
                language VARCHAR(10) NOT NULL,
                external_id VARCHAR(180) NOT NULL,
                payload JSONB NOT NULL,
                record_checksum VARCHAR(64) NOT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY (id)
            )
            SQL);
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX uniq_catalog_import_staged_set
            ON catalog_import_staged_sets (import_run_id, language, external_id)
            SQL);
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX uniq_catalog_import_staged_set_position
            ON catalog_import_staged_sets (import_run_id, position)
            SQL);
        $this->addSql(
            'CREATE INDEX idx_catalog_import_staged_sets_run ON catalog_import_staged_sets (import_run_id)',
        );
        $this->addSql(<<<'SQL'
            ALTER TABLE catalog_import_staged_sets
            ADD CONSTRAINT chk_catalog_import_staged_set_position
            CHECK (position >= 0)
            SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE catalog_import_staged_sets
            ADD CONSTRAINT chk_catalog_import_staged_set_checksum
            CHECK (record_checksum ~ '^[0-9a-f]{64}$')
            SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE catalog_import_staged_sets
            ADD CONSTRAINT chk_catalog_import_staged_set_payload
            CHECK (jsonb_typeof(payload) = 'object')
            SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE catalog_import_staged_sets
            ADD CONSTRAINT fk_catalog_import_staged_set_run
            FOREIGN KEY (import_run_id)
            REFERENCES catalog_import_runs (id)
            ON DELETE CASCADE
            NOT DEFERRABLE
            SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE catalog_import_staged_cards (
                id UUID NOT NULL,
                import_run_id UUID NOT NULL,
                position INT NOT NULL,
                language VARCHAR(10) NOT NULL,
                set_external_id VARCHAR(180) NOT NULL,
                external_id VARCHAR(180) NOT NULL,
                payload JSONB NOT NULL,
                record_checksum VARCHAR(64) NOT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY (id)
            )
            SQL);
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX uniq_catalog_import_staged_card
            ON catalog_import_staged_cards (import_run_id, language, external_id)
            SQL);
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX uniq_catalog_import_staged_card_position
            ON catalog_import_staged_cards (import_run_id, language, set_external_id, position)
            SQL);
        $this->addSql(
            'CREATE INDEX idx_catalog_import_staged_cards_run ON catalog_import_staged_cards (import_run_id)',
        );
        $this->addSql(<<<'SQL'
            ALTER TABLE catalog_import_staged_cards
            ADD CONSTRAINT chk_catalog_import_staged_card_position
            CHECK (position >= 0)
            SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE catalog_import_staged_cards
            ADD CONSTRAINT chk_catalog_import_staged_card_checksum
            CHECK (record_checksum ~ '^[0-9a-f]{64}$')
            SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE catalog_import_staged_cards
            ADD CONSTRAINT chk_catalog_import_staged_card_payload
            CHECK (jsonb_typeof(payload) = 'object')
            SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE catalog_import_staged_cards
            ADD CONSTRAINT fk_catalog_import_staged_card_run
            FOREIGN KEY (import_run_id)
            REFERENCES catalog_import_runs (id)
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

        $this->addSql('ALTER TABLE catalog_import_staged_cards DROP CONSTRAINT fk_catalog_import_staged_card_run');
        $this->addSql('ALTER TABLE catalog_import_staged_sets DROP CONSTRAINT fk_catalog_import_staged_set_run');
        $this->addSql('ALTER TABLE catalog_import_runs DROP CONSTRAINT FK_7CA2B4312CC33300');
        $this->addSql('DROP TABLE catalog_import_staged_cards');
        $this->addSql('DROP TABLE catalog_import_staged_sets');
        $this->addSql('DROP TABLE catalog_import_runs');
    }
}
