<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260801010000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Track order status history and the shipped-to-delivered lifecycle';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE order_status_history (id UUID NOT NULL, from_status VARCHAR(30) DEFAULT NULL, to_status VARCHAR(30) NOT NULL, occurred_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, order_id UUID NOT NULL, actor_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_471AD77E8D9F6D38 ON order_status_history (order_id)');
        $this->addSql('CREATE INDEX IDX_471AD77E10DAF24A ON order_status_history (actor_id)');
        $this->addSql('CREATE INDEX idx_order_status_history_order_time ON order_status_history (order_id, occurred_at)');
        $this->addSql('CREATE TABLE shipments (id UUID NOT NULL, delivered_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, status VARCHAR(20) NOT NULL, shipping_method VARCHAR(30) NOT NULL, shipped_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, order_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_shipment_order ON shipments (order_id)');
        $this->addSql('ALTER TABLE orders ADD completed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql("UPDATE orders SET paid_at = COALESCE(paid_at, created_at) WHERE status IN ('paid', 'preparing', 'shipped', 'delivered', 'completed', 'refunded', 'disputed')");
        $this->addSql("UPDATE orders SET completed_at = updated_at WHERE status = 'completed'");
        $this->addSql('INSERT INTO order_status_history (id, from_status, to_status, occurred_at, order_id, actor_id) SELECT gen_random_uuid(), NULL, status, updated_at, id, buyer_id FROM orders');
        $this->addSql("INSERT INTO shipments (id, delivered_at, updated_at, status, shipping_method, shipped_at, order_id) SELECT gen_random_uuid(), CASE WHEN status IN ('delivered', 'completed') THEN updated_at ELSE NULL END, updated_at, CASE WHEN status IN ('delivered', 'completed') THEN 'delivered' ELSE 'shipped' END, shipping_method, COALESCE(paid_at, created_at), id FROM orders WHERE status IN ('shipped', 'delivered', 'completed')");
        $this->addSql("ALTER TABLE order_status_history ADD CONSTRAINT chk_order_status_history_values CHECK (to_status IN ('pending_payment', 'paid', 'preparing', 'shipped', 'delivered', 'completed', 'cancelled', 'refunded', 'disputed') AND (from_status IS NULL OR from_status IN ('pending_payment', 'paid', 'preparing', 'shipped', 'delivered', 'completed', 'cancelled', 'refunded', 'disputed')))");
        $this->addSql('ALTER TABLE order_status_history ADD CONSTRAINT chk_order_status_history_change CHECK (from_status IS NULL OR from_status <> to_status)');
        $this->addSql("ALTER TABLE shipments ADD CONSTRAINT chk_shipment_status CHECK (status IN ('shipped', 'delivered'))");
        $this->addSql("ALTER TABLE shipments ADD CONSTRAINT chk_shipment_method CHECK (shipping_method IN ('letter_mail', 'parcel', 'pickup'))");
        $this->addSql("ALTER TABLE shipments ADD CONSTRAINT chk_shipment_timestamps CHECK ((status = 'shipped' AND delivered_at IS NULL) OR (status = 'delivered' AND delivered_at IS NOT NULL AND delivered_at >= shipped_at))");
        $this->addSql('ALTER TABLE orders DROP CONSTRAINT chk_order_paid_timestamp');
        $this->addSql("ALTER TABLE orders ADD CONSTRAINT chk_order_paid_timestamp CHECK (status NOT IN ('paid', 'preparing', 'shipped', 'delivered', 'completed', 'refunded', 'disputed') OR paid_at IS NOT NULL)");
        $this->addSql("ALTER TABLE orders ADD CONSTRAINT chk_order_completed_timestamp CHECK (status <> 'completed' OR completed_at IS NOT NULL)");
        $this->addSql('ALTER TABLE order_status_history ADD CONSTRAINT FK_471AD77E8D9F6D38 FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE order_status_history ADD CONSTRAINT FK_471AD77E10DAF24A FOREIGN KEY (actor_id) REFERENCES users (id) ON DELETE RESTRICT NOT DEFERRABLE');
        $this->addSql('ALTER TABLE shipments ADD CONSTRAINT FK_94699AD48D9F6D38 FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE RESTRICT NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE orders DROP CONSTRAINT chk_order_completed_timestamp');
        $this->addSql('ALTER TABLE orders DROP CONSTRAINT chk_order_paid_timestamp');
        $this->addSql("ALTER TABLE orders ADD CONSTRAINT chk_order_paid_timestamp CHECK (status <> 'paid' OR paid_at IS NOT NULL)");
        $this->addSql('ALTER TABLE order_status_history DROP CONSTRAINT FK_471AD77E8D9F6D38');
        $this->addSql('ALTER TABLE order_status_history DROP CONSTRAINT FK_471AD77E10DAF24A');
        $this->addSql('ALTER TABLE shipments DROP CONSTRAINT FK_94699AD48D9F6D38');
        $this->addSql('DROP TABLE order_status_history');
        $this->addSql('DROP TABLE shipments');
        $this->addSql('ALTER TABLE orders DROP completed_at');
    }
}
