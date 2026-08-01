<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260801095646 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add per-order refunds and cancellation metadata for shared payments';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE refunds (id UUID NOT NULL, provider_reference VARCHAR(180) DEFAULT NULL, failure_reason VARCHAR(500) DEFAULT NULL, processed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, status VARCHAR(20) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, amount INT NOT NULL, currency VARCHAR(3) NOT NULL, reason TEXT NOT NULL, payment_id UUID NOT NULL, order_id UUID NOT NULL, requested_by_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_7EE53AD94DA1E751 ON refunds (requested_by_id)');
        $this->addSql('CREATE INDEX idx_refunds_payment ON refunds (payment_id)');
        $this->addSql('CREATE INDEX idx_refunds_status ON refunds (status)');
        $this->addSql('CREATE UNIQUE INDEX uniq_refund_order ON refunds (order_id)');
        $this->addSql('ALTER TABLE refunds ADD CONSTRAINT FK_7EE53AD94C3A3BB FOREIGN KEY (payment_id) REFERENCES payments (id) ON DELETE RESTRICT NOT DEFERRABLE');
        $this->addSql('ALTER TABLE refunds ADD CONSTRAINT FK_7EE53AD98D9F6D38 FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE RESTRICT NOT DEFERRABLE');
        $this->addSql('ALTER TABLE refunds ADD CONSTRAINT FK_7EE53AD94DA1E751 FOREIGN KEY (requested_by_id) REFERENCES users (id) ON DELETE RESTRICT NOT DEFERRABLE');
        $this->addSql("ALTER TABLE refunds ADD CONSTRAINT chk_refund_status CHECK (status IN ('pending', 'succeeded', 'failed'))");
        $this->addSql("ALTER TABLE refunds ADD CONSTRAINT chk_refund_money CHECK (amount > 0 AND currency = 'EUR')");
        $this->addSql('ALTER TABLE refunds ADD CONSTRAINT chk_refund_reason CHECK (char_length(TRIM(reason)) BETWEEN 3 AND 500)');
        $this->addSql("ALTER TABLE refunds ADD CONSTRAINT chk_refund_result CHECK ((status = 'pending' AND provider_reference IS NULL AND failure_reason IS NULL AND processed_at IS NULL) OR (status = 'succeeded' AND provider_reference IS NOT NULL AND failure_reason IS NULL AND processed_at IS NOT NULL) OR (status = 'failed' AND provider_reference IS NULL AND failure_reason IS NOT NULL AND processed_at IS NOT NULL))");
        $this->addSql('ALTER TABLE orders ADD cancellation_reason TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE orders ADD cancelled_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE orders ADD cancelled_by_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE orders ADD CONSTRAINT FK_E52FFDEE187B2D12 FOREIGN KEY (cancelled_by_id) REFERENCES users (id) ON DELETE RESTRICT NOT DEFERRABLE');
        $this->addSql('CREATE INDEX IDX_E52FFDEE187B2D12 ON orders (cancelled_by_id)');
        $this->addSql('ALTER TABLE orders ADD CONSTRAINT chk_order_cancellation_metadata CHECK ((cancellation_reason IS NULL AND cancelled_at IS NULL AND cancelled_by_id IS NULL) OR (cancellation_reason IS NOT NULL AND char_length(TRIM(cancellation_reason)) BETWEEN 3 AND 500 AND cancelled_at IS NOT NULL AND cancelled_by_id IS NOT NULL))');
        $this->addSql('ALTER TABLE payment_transactions ADD order_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE payment_transactions ADD CONSTRAINT FK_8C58AD568D9F6D38 FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE RESTRICT NOT DEFERRABLE');
        $this->addSql('CREATE INDEX IDX_8C58AD568D9F6D38 ON payment_transactions (order_id)');
        $this->addSql('ALTER TABLE payment_transactions DROP CONSTRAINT chk_payment_transaction_type');
        $this->addSql("ALTER TABLE payment_transactions ADD CONSTRAINT chk_payment_transaction_type CHECK (type IN ('charge', 'refund'))");
        $this->addSql('ALTER TABLE payment_transactions DROP CONSTRAINT chk_payment_transaction_result');
        $this->addSql("ALTER TABLE payment_transactions ADD CONSTRAINT chk_payment_transaction_result CHECK (((status = 'succeeded' AND provider_reference IS NOT NULL AND failure_reason IS NULL) OR (status = 'failed' AND provider_reference IS NULL AND failure_reason IS NOT NULL)) AND ((type = 'charge' AND order_id IS NULL) OR (type = 'refund' AND order_id IS NOT NULL)))");
        $this->addSql("CREATE UNIQUE INDEX uniq_successful_refund_transaction_order ON payment_transactions (order_id) WHERE (((type)::text = 'refund'::text) AND ((status)::text = 'succeeded'::text))");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX uniq_successful_refund_transaction_order');
        $this->addSql("DELETE FROM payment_transactions WHERE type = 'refund'");
        $this->addSql('ALTER TABLE payment_transactions DROP CONSTRAINT chk_payment_transaction_result');
        $this->addSql("ALTER TABLE payment_transactions ADD CONSTRAINT chk_payment_transaction_result CHECK ((status = 'succeeded' AND provider_reference IS NOT NULL AND failure_reason IS NULL) OR (status = 'failed' AND provider_reference IS NULL AND failure_reason IS NOT NULL))");
        $this->addSql('ALTER TABLE payment_transactions DROP CONSTRAINT chk_payment_transaction_type');
        $this->addSql("ALTER TABLE payment_transactions ADD CONSTRAINT chk_payment_transaction_type CHECK (type IN ('charge'))");
        $this->addSql('ALTER TABLE refunds DROP CONSTRAINT FK_7EE53AD94C3A3BB');
        $this->addSql('ALTER TABLE refunds DROP CONSTRAINT FK_7EE53AD98D9F6D38');
        $this->addSql('ALTER TABLE refunds DROP CONSTRAINT FK_7EE53AD94DA1E751');
        $this->addSql('ALTER TABLE refunds DROP CONSTRAINT chk_refund_status');
        $this->addSql('ALTER TABLE refunds DROP CONSTRAINT chk_refund_money');
        $this->addSql('ALTER TABLE refunds DROP CONSTRAINT chk_refund_reason');
        $this->addSql('ALTER TABLE refunds DROP CONSTRAINT chk_refund_result');
        $this->addSql('DROP TABLE refunds');
        $this->addSql('ALTER TABLE orders DROP CONSTRAINT chk_order_cancellation_metadata');
        $this->addSql('ALTER TABLE orders DROP CONSTRAINT FK_E52FFDEE187B2D12');
        $this->addSql('DROP INDEX IDX_E52FFDEE187B2D12');
        $this->addSql('ALTER TABLE orders DROP cancellation_reason');
        $this->addSql('ALTER TABLE orders DROP cancelled_at');
        $this->addSql('ALTER TABLE orders DROP cancelled_by_id');
        $this->addSql('ALTER TABLE payment_transactions DROP CONSTRAINT FK_8C58AD568D9F6D38');
        $this->addSql('DROP INDEX IDX_8C58AD568D9F6D38');
        $this->addSql('ALTER TABLE payment_transactions DROP order_id');
    }
}
