<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260801000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create carts, atomic listing reservations, split orders and fake-provider payment records';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE listing_reservations (id UUID NOT NULL, completed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, released_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, checkout_reference UUID NOT NULL, expires_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, status VARCHAR(20) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, listing_id UUID NOT NULL, buyer_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_857F8C38D4619D1A ON listing_reservations (listing_id)');
        $this->addSql('CREATE INDEX IDX_857F8C386C755722 ON listing_reservations (buyer_id)');
        $this->addSql('CREATE INDEX idx_listing_reservations_buyer ON listing_reservations (buyer_id, created_at)');
        $this->addSql('CREATE INDEX idx_listing_reservations_expiration ON listing_reservations (status, expires_at)');
        $this->addSql('CREATE INDEX idx_listing_reservations_checkout ON listing_reservations (checkout_reference)');
        $this->addSql('CREATE UNIQUE INDEX uniq_active_listing_reservation ON listing_reservations (listing_id) WHERE ((status)::text = \'active\'::text)');
        $this->addSql('CREATE TABLE order_addresses (id UUID NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, full_name VARCHAR(180) NOT NULL, address_line_1 VARCHAR(180) NOT NULL, address_line_2 VARCHAR(180) DEFAULT NULL, postal_code VARCHAR(20) NOT NULL, city VARCHAR(120) NOT NULL, country_code VARCHAR(2) NOT NULL, order_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_order_shipping_address ON order_addresses (order_id)');
        $this->addSql('CREATE TABLE order_items (id UUID NOT NULL, price_amount INT NOT NULL, price_currency VARCHAR(3) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, order_id UUID NOT NULL, listing_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_62809DB08D9F6D38 ON order_items (order_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_order_item_listing ON order_items (listing_id)');
        $this->addSql('CREATE TABLE orders (id UUID NOT NULL, paid_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, shipping_method VARCHAR(30) NOT NULL, status VARCHAR(30) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, item_subtotal_amount INT NOT NULL, shipping_amount INT NOT NULL, service_fee_amount INT NOT NULL, total_amount INT NOT NULL, currency VARCHAR(3) NOT NULL, buyer_id UUID NOT NULL, seller_id UUID NOT NULL, payment_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_E52FFDEE6C755722 ON orders (buyer_id)');
        $this->addSql('CREATE INDEX IDX_E52FFDEE8DE820D9 ON orders (seller_id)');
        $this->addSql('CREATE INDEX idx_orders_buyer_created ON orders (buyer_id, created_at)');
        $this->addSql('CREATE INDEX idx_orders_seller_created ON orders (seller_id, created_at)');
        $this->addSql('CREATE INDEX idx_orders_payment ON orders (payment_id)');
        $this->addSql('CREATE TABLE payment_transactions (id UUID NOT NULL, type VARCHAR(20) NOT NULL, status VARCHAR(20) NOT NULL, amount INT NOT NULL, currency VARCHAR(3) NOT NULL, provider_reference VARCHAR(180) DEFAULT NULL, failure_reason VARCHAR(500) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, payment_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_8C58AD564C3A3BB ON payment_transactions (payment_id)');
        $this->addSql('CREATE INDEX idx_payment_transactions_payment_time ON payment_transactions (payment_id, created_at)');
        $this->addSql('CREATE TABLE payments (id UUID NOT NULL, provider_reference VARCHAR(180) DEFAULT NULL, completed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, provider_name VARCHAR(50) NOT NULL, status VARCHAR(20) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, amount INT NOT NULL, currency VARCHAR(3) NOT NULL, buyer_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_65D29B326C755722 ON payments (buyer_id)');
        $this->addSql('CREATE INDEX idx_payments_buyer_created ON payments (buyer_id, created_at)');
        $this->addSql('CREATE UNIQUE INDEX uniq_payment_provider_reference ON payments (provider_name, provider_reference) WHERE provider_reference IS NOT NULL');
        $this->addSql('CREATE TABLE shopping_cart_items (id UUID NOT NULL, added_price_amount INT NOT NULL, added_price_currency VARCHAR(3) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, cart_id UUID NOT NULL, listing_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_A13B63131AD5CDBF ON shopping_cart_items (cart_id)');
        $this->addSql('CREATE INDEX IDX_A13B6313D4619D1A ON shopping_cart_items (listing_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_cart_listing ON shopping_cart_items (cart_id, listing_id)');
        $this->addSql('CREATE TABLE shopping_carts (id UUID NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, buyer_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_shopping_cart_buyer ON shopping_carts (buyer_id)');
        $this->addSql("ALTER TABLE listing_reservations ADD CONSTRAINT chk_listing_reservation_status CHECK (status IN ('active', 'completed', 'released', 'expired'))");
        $this->addSql('ALTER TABLE listing_reservations ADD CONSTRAINT chk_listing_reservation_expiration CHECK (expires_at > created_at)');
        $this->addSql("ALTER TABLE listing_reservations ADD CONSTRAINT chk_listing_reservation_timestamps CHECK ((status = 'active' AND completed_at IS NULL AND released_at IS NULL) OR (status = 'completed' AND completed_at IS NOT NULL AND released_at IS NULL) OR (status IN ('released', 'expired') AND completed_at IS NULL AND released_at IS NOT NULL))");
        $this->addSql("ALTER TABLE order_addresses ADD CONSTRAINT chk_order_address_country CHECK (country_code IN ('NL', 'BE'))");
        $this->addSql("ALTER TABLE order_addresses ADD CONSTRAINT chk_order_address_required CHECK (TRIM(full_name) <> '' AND TRIM(address_line_1) <> '' AND TRIM(postal_code) <> '' AND TRIM(city) <> '')");
        $this->addSql("ALTER TABLE order_items ADD CONSTRAINT chk_order_item_price CHECK (price_amount > 0 AND price_currency = 'EUR')");
        $this->addSql("ALTER TABLE orders ADD CONSTRAINT chk_order_status CHECK (status IN ('pending_payment', 'paid', 'preparing', 'shipped', 'delivered', 'completed', 'cancelled', 'refunded', 'disputed'))");
        $this->addSql("ALTER TABLE orders ADD CONSTRAINT chk_order_shipping_method CHECK (shipping_method IN ('letter_mail', 'parcel', 'pickup'))");
        $this->addSql("ALTER TABLE orders ADD CONSTRAINT chk_order_money CHECK (item_subtotal_amount > 0 AND shipping_amount >= 0 AND service_fee_amount >= 0 AND total_amount = item_subtotal_amount + shipping_amount + service_fee_amount AND currency = 'EUR')");
        $this->addSql('ALTER TABLE orders ADD CONSTRAINT chk_order_parties CHECK (buyer_id <> seller_id)');
        $this->addSql("ALTER TABLE orders ADD CONSTRAINT chk_order_paid_timestamp CHECK (status <> 'paid' OR paid_at IS NOT NULL)");
        $this->addSql("ALTER TABLE payment_transactions ADD CONSTRAINT chk_payment_transaction_type CHECK (type IN ('charge'))");
        $this->addSql("ALTER TABLE payment_transactions ADD CONSTRAINT chk_payment_transaction_status CHECK (status IN ('succeeded', 'failed'))");
        $this->addSql("ALTER TABLE payment_transactions ADD CONSTRAINT chk_payment_transaction_money CHECK (amount > 0 AND currency = 'EUR')");
        $this->addSql("ALTER TABLE payment_transactions ADD CONSTRAINT chk_payment_transaction_result CHECK ((status = 'succeeded' AND provider_reference IS NOT NULL AND failure_reason IS NULL) OR (status = 'failed' AND provider_reference IS NULL AND failure_reason IS NOT NULL))");
        $this->addSql("ALTER TABLE payments ADD CONSTRAINT chk_payment_status CHECK (status IN ('pending', 'succeeded', 'failed', 'refunded'))");
        $this->addSql("ALTER TABLE payments ADD CONSTRAINT chk_payment_money CHECK (amount > 0 AND currency = 'EUR')");
        $this->addSql("ALTER TABLE payments ADD CONSTRAINT chk_payment_provider CHECK (TRIM(provider_name) <> '')");
        $this->addSql("ALTER TABLE payments ADD CONSTRAINT chk_payment_result CHECK ((status = 'pending' AND provider_reference IS NULL AND completed_at IS NULL) OR (status = 'succeeded' AND provider_reference IS NOT NULL AND completed_at IS NOT NULL) OR (status IN ('failed', 'refunded') AND completed_at IS NOT NULL))");
        $this->addSql("ALTER TABLE shopping_cart_items ADD CONSTRAINT chk_cart_added_price CHECK (added_price_amount > 0 AND added_price_currency = 'EUR')");
        $this->addSql('ALTER TABLE listing_reservations ADD CONSTRAINT FK_857F8C38D4619D1A FOREIGN KEY (listing_id) REFERENCES listings (id) ON DELETE RESTRICT NOT DEFERRABLE');
        $this->addSql('ALTER TABLE listing_reservations ADD CONSTRAINT FK_857F8C386C755722 FOREIGN KEY (buyer_id) REFERENCES users (id) ON DELETE RESTRICT NOT DEFERRABLE');
        $this->addSql('ALTER TABLE order_addresses ADD CONSTRAINT FK_D34D0EEE8D9F6D38 FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE order_items ADD CONSTRAINT FK_62809DB08D9F6D38 FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE order_items ADD CONSTRAINT FK_62809DB0D4619D1A FOREIGN KEY (listing_id) REFERENCES listings (id) ON DELETE RESTRICT NOT DEFERRABLE');
        $this->addSql('ALTER TABLE orders ADD CONSTRAINT FK_E52FFDEE6C755722 FOREIGN KEY (buyer_id) REFERENCES users (id) ON DELETE RESTRICT NOT DEFERRABLE');
        $this->addSql('ALTER TABLE orders ADD CONSTRAINT FK_E52FFDEE8DE820D9 FOREIGN KEY (seller_id) REFERENCES users (id) ON DELETE RESTRICT NOT DEFERRABLE');
        $this->addSql('ALTER TABLE orders ADD CONSTRAINT FK_E52FFDEE4C3A3BB FOREIGN KEY (payment_id) REFERENCES payments (id) ON DELETE RESTRICT NOT DEFERRABLE');
        $this->addSql('ALTER TABLE payment_transactions ADD CONSTRAINT FK_8C58AD564C3A3BB FOREIGN KEY (payment_id) REFERENCES payments (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE payments ADD CONSTRAINT FK_65D29B326C755722 FOREIGN KEY (buyer_id) REFERENCES users (id) ON DELETE RESTRICT NOT DEFERRABLE');
        $this->addSql('ALTER TABLE shopping_cart_items ADD CONSTRAINT FK_A13B63131AD5CDBF FOREIGN KEY (cart_id) REFERENCES shopping_carts (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE shopping_cart_items ADD CONSTRAINT FK_A13B6313D4619D1A FOREIGN KEY (listing_id) REFERENCES listings (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE shopping_carts ADD CONSTRAINT FK_4FA232F66C755722 FOREIGN KEY (buyer_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE listing_reservations DROP CONSTRAINT FK_857F8C38D4619D1A');
        $this->addSql('ALTER TABLE listing_reservations DROP CONSTRAINT FK_857F8C386C755722');
        $this->addSql('ALTER TABLE order_addresses DROP CONSTRAINT FK_D34D0EEE8D9F6D38');
        $this->addSql('ALTER TABLE order_items DROP CONSTRAINT FK_62809DB08D9F6D38');
        $this->addSql('ALTER TABLE order_items DROP CONSTRAINT FK_62809DB0D4619D1A');
        $this->addSql('ALTER TABLE orders DROP CONSTRAINT FK_E52FFDEE6C755722');
        $this->addSql('ALTER TABLE orders DROP CONSTRAINT FK_E52FFDEE8DE820D9');
        $this->addSql('ALTER TABLE orders DROP CONSTRAINT FK_E52FFDEE4C3A3BB');
        $this->addSql('ALTER TABLE payment_transactions DROP CONSTRAINT FK_8C58AD564C3A3BB');
        $this->addSql('ALTER TABLE payments DROP CONSTRAINT FK_65D29B326C755722');
        $this->addSql('ALTER TABLE shopping_cart_items DROP CONSTRAINT FK_A13B63131AD5CDBF');
        $this->addSql('ALTER TABLE shopping_cart_items DROP CONSTRAINT FK_A13B6313D4619D1A');
        $this->addSql('ALTER TABLE shopping_carts DROP CONSTRAINT FK_4FA232F66C755722');
        $this->addSql('DROP TABLE listing_reservations');
        $this->addSql('DROP TABLE order_addresses');
        $this->addSql('DROP TABLE order_items');
        $this->addSql('DROP TABLE orders');
        $this->addSql('DROP TABLE payment_transactions');
        $this->addSql('DROP TABLE payments');
        $this->addSql('DROP TABLE shopping_cart_items');
        $this->addSql('DROP TABLE shopping_carts');
    }
}
