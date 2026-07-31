<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260731235000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create marketplace listings with photos, shipping options, workflow status and price history';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'CREATE TABLE listings ('
            .'id UUID NOT NULL, seller_id UUID NOT NULL, collection_item_id UUID NOT NULL, '
            .'price_amount INT NOT NULL, price_currency VARCHAR(3) NOT NULL, description TEXT DEFAULT NULL, '
            .'expires_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, status VARCHAR(30) NOT NULL, '
            .'published_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, '
            .'cancelled_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, '
            .'created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, '
            .'updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id), '
            .'CONSTRAINT chk_listing_price CHECK (price_amount > 0 AND price_currency = \'EUR\'), '
            .'CONSTRAINT chk_listing_expiration CHECK (expires_at IS NULL OR expires_at > created_at), '
            ."CONSTRAINT chk_listing_status CHECK (status IN ('draft', 'pending_review', 'active', 'reserved', "
            ."'sold', 'cancelled', 'expired', 'suspended')))",
        );
        $this->addSql('CREATE INDEX idx_listings_status_published ON listings (status, published_at)');
        $this->addSql('CREATE INDEX idx_listings_seller_status ON listings (seller_id, status)');
        $this->addSql('CREATE INDEX idx_listings_collection_item ON listings (collection_item_id)');
        $this->addSql(
            "CREATE UNIQUE INDEX uniq_collection_item_blocking_listing ON listings (collection_item_id) WHERE status IN ('pending_review', 'active', 'reserved', 'sold')",
        );
        $this->addSql(
            'ALTER TABLE listings ADD CONSTRAINT FK_LISTING_SELLER FOREIGN KEY (seller_id) REFERENCES users (id) '
            .'ON DELETE RESTRICT NOT DEFERRABLE INITIALLY IMMEDIATE',
        );
        $this->addSql(
            'ALTER TABLE listings ADD CONSTRAINT FK_LISTING_COLLECTION_ITEM '
            .'FOREIGN KEY (collection_item_id) REFERENCES collection_items (id) '
            .'ON DELETE RESTRICT NOT DEFERRABLE INITIALLY IMMEDIATE',
        );

        $this->addSql(
            'CREATE TABLE listing_images ('
            .'id UUID NOT NULL, listing_id UUID NOT NULL, side VARCHAR(10) NOT NULL, url VARCHAR(2048) NOT NULL, '
            .'created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id), '
            ."CONSTRAINT chk_listing_image_side CHECK (side IN ('front', 'back')))",
        );
        $this->addSql('CREATE UNIQUE INDEX uniq_listing_image_side ON listing_images (listing_id, side)');
        $this->addSql(
            'ALTER TABLE listing_images ADD CONSTRAINT FK_LISTING_IMAGE_LISTING '
            .'FOREIGN KEY (listing_id) REFERENCES listings (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE',
        );

        $this->addSql(
            'CREATE TABLE listing_shipping_methods ('
            .'id UUID NOT NULL, listing_id UUID NOT NULL, code VARCHAR(30) NOT NULL, '
            .'price_amount INT NOT NULL, price_currency VARCHAR(3) NOT NULL, '
            .'created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id), '
            ."CONSTRAINT chk_listing_shipping_code CHECK (code IN ('letter_mail', 'parcel', 'pickup')), "
            .'CONSTRAINT chk_listing_shipping_price CHECK (price_amount >= 0 AND price_currency = \'EUR\'), '
            ."CONSTRAINT chk_listing_pickup_price CHECK (code <> 'pickup' OR price_amount = 0))",
        );
        $this->addSql(
            'CREATE UNIQUE INDEX uniq_listing_shipping_method ON listing_shipping_methods (listing_id, code)',
        );
        $this->addSql(
            'ALTER TABLE listing_shipping_methods ADD CONSTRAINT FK_LISTING_SHIPPING_LISTING '
            .'FOREIGN KEY (listing_id) REFERENCES listings (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE',
        );

        $this->addSql(
            'CREATE TABLE listing_price_history ('
            .'id UUID NOT NULL, listing_id UUID NOT NULL, previous_amount INT DEFAULT NULL, '
            .'new_amount INT NOT NULL, currency VARCHAR(3) NOT NULL, '
            .'changed_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id), '
            .'CONSTRAINT chk_listing_price_history CHECK ('
            .'(previous_amount IS NULL OR previous_amount > 0) AND new_amount > 0 AND currency = \'EUR\'))',
        );
        $this->addSql(
            'CREATE INDEX idx_listing_price_history_time ON listing_price_history (listing_id, changed_at)',
        );
        $this->addSql(
            'ALTER TABLE listing_price_history ADD CONSTRAINT FK_LISTING_PRICE_HISTORY_LISTING '
            .'FOREIGN KEY (listing_id) REFERENCES listings (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE',
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE listing_price_history');
        $this->addSql('DROP TABLE listing_shipping_methods');
        $this->addSql('DROP TABLE listing_images');
        $this->addSql('DROP TABLE listings');
    }
}
