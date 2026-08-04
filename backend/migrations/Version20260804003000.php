<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260804003000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add catalog discrepancy audit records and evidence-backed coverage snapshots';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->platform instanceof PostgreSQLPlatform,
            'This migration can only be executed safely on PostgreSQL.',
        );

        $this->addSql(<<<'SQL'
            CREATE TABLE catalog_discrepancies (
                id UUID NOT NULL,
                last_import_run_id UUID NOT NULL,
                resolved_by_id UUID DEFAULT NULL,
                provider VARCHAR(100) NOT NULL,
                scope_key VARCHAR(64) NOT NULL,
                language VARCHAR(10) DEFAULT NULL,
                region VARCHAR(50) DEFAULT NULL,
                entity_type VARCHAR(30) NOT NULL,
                external_reference VARCHAR(500) DEFAULT NULL,
                discrepancy_type VARCHAR(50) NOT NULL,
                severity VARCHAR(30) NOT NULL,
                message TEXT NOT NULL,
                fingerprint VARCHAR(64) NOT NULL,
                resolution_status VARCHAR(30) NOT NULL,
                occurrence_count INT NOT NULL,
                resolution_reason TEXT DEFAULT NULL,
                resolved_by_identifier VARCHAR(36) DEFAULT NULL,
                resolved_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                resolution_history JSONB NOT NULL,
                last_seen_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY (id)
            )
            SQL);
        $this->addSql(
            'CREATE UNIQUE INDEX uniq_catalog_discrepancy_fingerprint ON catalog_discrepancies (fingerprint)',
        );
        $this->addSql(<<<'SQL'
            CREATE INDEX idx_catalog_discrepancies_scope_status
            ON catalog_discrepancies (provider, scope_key, language, region, resolution_status, severity)
            SQL);
        $this->addSql(
            'CREATE INDEX idx_catalog_discrepancies_last_seen ON catalog_discrepancies (last_seen_at)',
        );
        $this->addSql(<<<'SQL'
            CREATE INDEX idx_catalog_discrepancies_import_run
            ON catalog_discrepancies (last_import_run_id)
            SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE catalog_discrepancies
            ADD CONSTRAINT chk_catalog_discrepancy_scope_key
            CHECK (scope_key ~ '^[0-9a-f]{64}$')
            SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE catalog_discrepancies
            ADD CONSTRAINT chk_catalog_discrepancy_fingerprint
            CHECK (fingerprint ~ '^[0-9a-f]{64}$')
            SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE catalog_discrepancies
            ADD CONSTRAINT chk_catalog_discrepancy_entity_type
            CHECK (entity_type IN ('import', 'scope', 'set', 'card', 'pokemon', 'artist', 'image', 'price'))
            SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE catalog_discrepancies
            ADD CONSTRAINT chk_catalog_discrepancy_type
            CHECK (discrepancy_type IN (
                'missing_set',
                'missing_card',
                'missing_pokemon',
                'missing_form',
                'missing_artist',
                'missing_image',
                'missing_card_number',
                'duplicate_card',
                'duplicate_set',
                'conflicting_name',
                'conflicting_number',
                'conflicting_release_date',
                'conflicting_total',
                'conflicting_source_version',
                'orphan_card',
                'unmapped_pokemon',
                'low_resolution_image',
                'unknown_variant',
                'provider_error'
            ))
            SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE catalog_discrepancies
            ADD CONSTRAINT chk_catalog_discrepancy_severity
            CHECK (severity IN ('critical', 'high', 'medium', 'low', 'informational'))
            SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE catalog_discrepancies
            ADD CONSTRAINT chk_catalog_discrepancy_resolution_status
            CHECK (resolution_status IN ('open', 'accepted', 'resolved', 'ignored_with_reason', 'blocked_external'))
            SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE catalog_discrepancies
            ADD CONSTRAINT chk_catalog_discrepancy_occurrences
            CHECK (occurrence_count > 0)
            SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE catalog_discrepancies
            ADD CONSTRAINT chk_catalog_discrepancy_resolution_history
            CHECK (jsonb_typeof(resolution_history) = 'array')
            SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE catalog_discrepancies
            ADD CONSTRAINT chk_catalog_discrepancy_resolver_identifier
            CHECK (
                resolved_by_identifier IS NULL
                OR resolved_by_identifier ~* '^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$'
            )
            SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE catalog_discrepancies
            ADD CONSTRAINT chk_catalog_discrepancy_resolution
            CHECK (
                (
                    resolution_status = 'open'
                    AND resolution_reason IS NULL
                    AND resolved_by_identifier IS NULL
                    AND resolved_at IS NULL
                )
                OR (
                    resolution_status <> 'open'
                    AND resolution_reason IS NOT NULL
                    AND resolved_by_identifier IS NOT NULL
                    AND resolved_at IS NOT NULL
                )
            )
            SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE catalog_discrepancies
            ADD CONSTRAINT fk_catalog_discrepancy_import_run
            FOREIGN KEY (last_import_run_id)
            REFERENCES catalog_import_runs (id)
            ON DELETE RESTRICT
            NOT DEFERRABLE
            SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE catalog_discrepancies
            ADD CONSTRAINT fk_catalog_discrepancy_resolved_by
            FOREIGN KEY (resolved_by_id)
            REFERENCES users (id)
            ON DELETE SET NULL
            NOT DEFERRABLE
            SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE catalog_coverage_snapshots (
                id UUID NOT NULL,
                import_run_id UUID NOT NULL,
                approved_by_id UUID DEFAULT NULL,
                approved_by_identifier VARCHAR(36) NOT NULL,
                provider VARCHAR(100) NOT NULL,
                region VARCHAR(50) NOT NULL,
                language VARCHAR(10) NOT NULL,
                evidence_source VARCHAR(180) NOT NULL,
                evidence_version VARCHAR(180) NOT NULL,
                evidence_reference VARCHAR(1024) NOT NULL,
                data_cutoff TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                manifest_checksum VARCHAR(64) NOT NULL,
                dimensions JSONB NOT NULL,
                release_gate_status VARCHAR(40) NOT NULL,
                blocking_reasons JSONB NOT NULL,
                unresolved_critical_discrepancy_count INT NOT NULL,
                externally_blocked_critical_discrepancy_count INT NOT NULL,
                duplicate_set_count INT NOT NULL,
                duplicate_card_count INT NOT NULL,
                orphan_card_count INT NOT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY (id)
            )
            SQL);
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX uniq_catalog_coverage_manifest_snapshot
            ON catalog_coverage_snapshots (import_run_id, manifest_checksum)
            SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX idx_catalog_coverage_scope_created
            ON catalog_coverage_snapshots (provider, region, language, created_at)
            SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX idx_catalog_coverage_release_status
            ON catalog_coverage_snapshots (release_gate_status, created_at)
            SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE catalog_coverage_snapshots
            ADD CONSTRAINT chk_catalog_coverage_manifest_checksum
            CHECK (manifest_checksum ~ '^[0-9a-f]{64}$')
            SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE catalog_coverage_snapshots
            ADD CONSTRAINT chk_catalog_coverage_approver_identifier
            CHECK (approved_by_identifier ~* '^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$')
            SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE catalog_coverage_snapshots
            ADD CONSTRAINT chk_catalog_coverage_dimensions
            CHECK (jsonb_typeof(dimensions) = 'object')
            SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE catalog_coverage_snapshots
            ADD CONSTRAINT chk_catalog_coverage_blocking_reasons
            CHECK (jsonb_typeof(blocking_reasons) = 'array')
            SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE catalog_coverage_snapshots
            ADD CONSTRAINT chk_catalog_coverage_release_gate_status
            CHECK (release_gate_status IN (
                'passed',
                'blocked_data_gaps',
                'blocked_discrepancies',
                'not_verified',
                'blocked_external'
            ))
            SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE catalog_coverage_snapshots
            ADD CONSTRAINT chk_catalog_coverage_counts
            CHECK (
                unresolved_critical_discrepancy_count >= 0
                AND externally_blocked_critical_discrepancy_count >= 0
                AND externally_blocked_critical_discrepancy_count <= unresolved_critical_discrepancy_count
                AND duplicate_set_count >= 0
                AND duplicate_card_count >= 0
                AND orphan_card_count >= 0
            )
            SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE catalog_coverage_snapshots
            ADD CONSTRAINT fk_catalog_coverage_import_run
            FOREIGN KEY (import_run_id)
            REFERENCES catalog_import_runs (id)
            ON DELETE RESTRICT
            NOT DEFERRABLE
            SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE catalog_coverage_snapshots
            ADD CONSTRAINT fk_catalog_coverage_approved_by
            FOREIGN KEY (approved_by_id)
            REFERENCES users (id)
            ON DELETE SET NULL
            NOT DEFERRABLE
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->platform instanceof PostgreSQLPlatform,
            'This migration can only be executed safely on PostgreSQL.',
        );

        $this->addSql('ALTER TABLE catalog_coverage_snapshots DROP CONSTRAINT fk_catalog_coverage_approved_by');
        $this->addSql('ALTER TABLE catalog_coverage_snapshots DROP CONSTRAINT fk_catalog_coverage_import_run');
        $this->addSql('ALTER TABLE catalog_discrepancies DROP CONSTRAINT fk_catalog_discrepancy_resolved_by');
        $this->addSql('ALTER TABLE catalog_discrepancies DROP CONSTRAINT fk_catalog_discrepancy_import_run');
        $this->addSql('DROP TABLE catalog_coverage_snapshots');
        $this->addSql('DROP TABLE catalog_discrepancies');
    }
}
