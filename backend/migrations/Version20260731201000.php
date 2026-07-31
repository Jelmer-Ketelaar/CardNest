<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260731201000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create user-owned physical collection items';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'CREATE TABLE collection_items ('
            .'id UUID NOT NULL, '
            .'owner_id UUID NOT NULL, '
            .'catalog_card_id UUID NOT NULL, '
            .'variant_id UUID NOT NULL, '
            .'language VARCHAR(5) NOT NULL, '
            .'condition VARCHAR(20) NOT NULL, '
            .'purchase_price INT DEFAULT NULL, '
            .'purchase_currency VARCHAR(3) DEFAULT NULL, '
            .'purchased_at DATE DEFAULT NULL, '
            .'seller_name VARCHAR(180) DEFAULT NULL, '
            .'notes TEXT DEFAULT NULL, '
            .'status VARCHAR(20) NOT NULL, '
            .'created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, '
            .'updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, '
            .'PRIMARY KEY(id), '
            .'CONSTRAINT chk_collection_purchase_price CHECK (purchase_price IS NULL OR purchase_price >= 0), '
            .'CONSTRAINT chk_collection_purchase_money CHECK ('
            .'(purchase_price IS NULL AND purchase_currency IS NULL) '
            .'OR (purchase_price IS NOT NULL AND purchase_currency IS NOT NULL))'
            .')',
        );
        $this->addSql('CREATE INDEX idx_collection_items_owner_status ON collection_items (owner_id, status)');
        $this->addSql('CREATE INDEX idx_collection_items_card ON collection_items (catalog_card_id)');
        $this->addSql('CREATE INDEX idx_collection_items_variant ON collection_items (variant_id)');
        $this->addSql(
            'CREATE INDEX idx_collection_items_duplicates '
            .'ON collection_items (owner_id, catalog_card_id, language, variant_id, status)',
        );
        $this->addSql(
            'ALTER TABLE collection_items ADD CONSTRAINT FK_COLLECTION_ITEM_OWNER '
            .'FOREIGN KEY (owner_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE',
        );
        $this->addSql(
            'ALTER TABLE collection_items ADD CONSTRAINT FK_COLLECTION_ITEM_CARD '
            .'FOREIGN KEY (catalog_card_id) REFERENCES catalog_cards (id) '
            .'ON DELETE RESTRICT NOT DEFERRABLE INITIALLY IMMEDIATE',
        );
        $this->addSql(
            'ALTER TABLE collection_items ADD CONSTRAINT FK_COLLECTION_ITEM_VARIANT '
            .'FOREIGN KEY (variant_id) REFERENCES card_variants (id) '
            .'ON DELETE RESTRICT NOT DEFERRABLE INITIALLY IMMEDIATE',
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE collection_items');
    }
}
