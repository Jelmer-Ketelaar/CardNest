<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260803230031 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Reconcile redundant and unstable indexes and remove the payment bootstrap default';
    }

    public function isTransactional(): bool
    {
        return false;
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->platform instanceof PostgreSQLPlatform,
            'This migration can only be executed safely on PostgreSQL.',
        );

        $this->addSql('DROP INDEX CONCURRENTLY IF EXISTS idx_artists_normalized_name');
        $this->addSql('ALTER TABLE payments ALTER finalization_status DROP DEFAULT');
        $this->addSql('ALTER INDEX IF EXISTS idx_65d29b324ccb540a RENAME TO idx_payments_checkout_cart');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            !$this->platform instanceof PostgreSQLPlatform,
            'This migration can only be executed safely on PostgreSQL.',
        );

        $this->addSql(
            'CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_artists_normalized_name ON artists (normalized_name)',
        );
        $this->addSql('ALTER TABLE payments ALTER finalization_status SET DEFAULT \'not_required\'');
        $this->addSql('ALTER INDEX IF EXISTS idx_payments_checkout_cart RENAME TO idx_65d29b324ccb540a');
    }
}
