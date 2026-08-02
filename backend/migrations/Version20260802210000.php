<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260802210000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Persist recoverable checkout payment intents and finalization state';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE payments ADD idempotency_key VARCHAR(255) DEFAULT NULL, ADD request_fingerprint VARCHAR(64) DEFAULT NULL, ADD cart_fingerprint VARCHAR(64) DEFAULT NULL, ADD finalization_status VARCHAR(30) DEFAULT 'not_required' NOT NULL, ADD provider_result_recorded_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, ADD finalization_attempted_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, ADD finalized_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, ADD checkout_cart_id UUID DEFAULT NULL");
        $this->addSql("UPDATE payments SET idempotency_key = 'legacy_' || replace(id::text, '-', ''), provider_result_recorded_at = completed_at");
        $this->addSql('ALTER TABLE payments ALTER idempotency_key SET NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX uniq_payment_idempotency_key ON payments (idempotency_key)');
        $this->addSql('CREATE INDEX idx_payments_finalization ON payments (finalization_status, created_at)');
        $this->addSql('CREATE INDEX IDX_65D29B324CCB540A ON payments (checkout_cart_id)');
        $this->addSql('ALTER TABLE payments ADD CONSTRAINT FK_65D29B324CCB540A FOREIGN KEY (checkout_cart_id) REFERENCES shopping_carts (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE payments DROP CONSTRAINT FK_65D29B324CCB540A');
        $this->addSql('DROP INDEX uniq_payment_idempotency_key');
        $this->addSql('DROP INDEX idx_payments_finalization');
        $this->addSql('DROP INDEX IDX_65D29B324CCB540A');
        $this->addSql('ALTER TABLE payments DROP idempotency_key, DROP request_fingerprint, DROP cart_fingerprint, DROP finalization_status, DROP provider_result_recorded_at, DROP finalization_attempted_at, DROP finalized_at, DROP checkout_cart_id');
    }
}
