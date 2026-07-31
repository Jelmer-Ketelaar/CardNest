<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260731230000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create owner-scoped wishlists with match criteria and fulfillment progress';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'CREATE TABLE wishlists ('
            .'id UUID NOT NULL, owner_id UUID NOT NULL, name VARCHAR(180) NOT NULL, '
            .'created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, '
            .'updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))',
        );
        $this->addSql('CREATE INDEX idx_wishlists_owner ON wishlists (owner_id)');
        $this->addSql(
            'ALTER TABLE wishlists ADD CONSTRAINT FK_WISHLIST_OWNER '
            .'FOREIGN KEY (owner_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE',
        );

        $this->addSql(
            'CREATE TABLE wishlist_items ('
            .'id UUID NOT NULL, wishlist_id UUID NOT NULL, catalog_card_id UUID NOT NULL, variant_id UUID DEFAULT NULL, '
            .'desired_language VARCHAR(5) DEFAULT NULL, minimum_condition VARCHAR(20) DEFAULT NULL, '
            .'max_price INT DEFAULT NULL, max_currency VARCHAR(3) DEFAULT NULL, '
            .'desired_quantity INT NOT NULL, fulfilled_quantity INT NOT NULL, priority VARCHAR(20) NOT NULL, '
            .'notes TEXT DEFAULT NULL, completed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, '
            .'created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, '
            .'updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id), '
            .'CONSTRAINT chk_wishlist_quantity CHECK (desired_quantity BETWEEN 1 AND 100), '
            .'CONSTRAINT chk_wishlist_fulfilled CHECK ('
            .'fulfilled_quantity >= 0 AND fulfilled_quantity <= desired_quantity), '
            .'CONSTRAINT chk_wishlist_max_money CHECK ('
            .'(max_price IS NULL AND max_currency IS NULL) OR '
            ."(max_price >= 0 AND max_currency = 'EUR')), "
            ."CONSTRAINT chk_wishlist_priority CHECK (priority IN ('low', 'normal', 'high', 'wanted')), "
            .'CONSTRAINT chk_wishlist_language CHECK (desired_language IS NULL OR desired_language IN '
            ."('en', 'nl', 'ja', 'ko', 'de', 'fr', 'it', 'es', 'pt', 'zh')), "
            .'CONSTRAINT chk_wishlist_condition CHECK (minimum_condition IS NULL OR minimum_condition IN '
            ."('mint', 'near_mint', 'excellent', 'good', 'light_played', 'played', 'poor')))",
        );
        $this->addSql('CREATE INDEX idx_wishlist_items_wishlist ON wishlist_items (wishlist_id)');
        $this->addSql(
            'CREATE INDEX idx_wishlist_items_open_card ON wishlist_items (catalog_card_id, completed_at)',
        );
        $this->addSql(
            'ALTER TABLE wishlist_items ADD CONSTRAINT FK_WISHLIST_ITEM_WISHLIST '
            .'FOREIGN KEY (wishlist_id) REFERENCES wishlists (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE',
        );
        $this->addSql(
            'ALTER TABLE wishlist_items ADD CONSTRAINT FK_WISHLIST_ITEM_CARD '
            .'FOREIGN KEY (catalog_card_id) REFERENCES catalog_cards (id) '
            .'ON DELETE RESTRICT NOT DEFERRABLE INITIALLY IMMEDIATE',
        );
        $this->addSql(
            'ALTER TABLE wishlist_items ADD CONSTRAINT FK_WISHLIST_ITEM_VARIANT '
            .'FOREIGN KEY (variant_id) REFERENCES card_variants (id) '
            .'ON DELETE RESTRICT NOT DEFERRABLE INITIALLY IMMEDIATE',
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE wishlist_items');
        $this->addSql('DROP TABLE wishlists');
    }
}
