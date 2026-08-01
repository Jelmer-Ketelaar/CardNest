<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260801160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Introduce private collections and associate physical items with their collection';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE collections (id UUID NOT NULL, owner_id UUID NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_collections_owner ON collections (owner_id)');
        $this->addSql('ALTER TABLE collections ADD CONSTRAINT FK_COLLECTION_OWNER FOREIGN KEY (owner_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');

        // Existing users receive a deterministic collection record without requiring a database UUID extension.
        $this->addSql('INSERT INTO collections (id, owner_id, created_at, updated_at) SELECT id, id, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP FROM users');

        $this->addSql('ALTER TABLE collection_items ADD collection_id UUID DEFAULT NULL');
        $this->addSql('UPDATE collection_items item SET collection_id = collection.id FROM collections collection WHERE collection.owner_id = item.owner_id');
        $this->addSql('ALTER TABLE collection_items ALTER collection_id SET NOT NULL');
        $this->addSql('ALTER TABLE collection_items ADD CONSTRAINT FK_COLLECTION_ITEM_COLLECTION FOREIGN KEY (collection_id) REFERENCES collections (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql('DROP INDEX idx_collection_items_owner_status');
        $this->addSql('DROP INDEX idx_collection_items_duplicates');
        $this->addSql('ALTER TABLE collection_items DROP CONSTRAINT FK_COLLECTION_ITEM_OWNER');
        $this->addSql('ALTER TABLE collection_items DROP owner_id');
        $this->addSql('CREATE INDEX idx_collection_items_collection_status ON collection_items (collection_id, status)');
        $this->addSql('CREATE INDEX idx_collection_items_duplicates ON collection_items (collection_id, catalog_card_id, language, variant_id, status)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE collection_items ADD owner_id UUID DEFAULT NULL');
        $this->addSql('UPDATE collection_items item SET owner_id = collection.owner_id FROM collections collection WHERE collection.id = item.collection_id');
        $this->addSql('ALTER TABLE collection_items ALTER owner_id SET NOT NULL');
        $this->addSql('ALTER TABLE collection_items ADD CONSTRAINT FK_COLLECTION_ITEM_OWNER FOREIGN KEY (owner_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX idx_collection_items_owner_status ON collection_items (owner_id, status)');
        $this->addSql('DROP INDEX idx_collection_items_duplicates');
        $this->addSql('CREATE INDEX idx_collection_items_duplicates ON collection_items (owner_id, catalog_card_id, language, variant_id, status)');

        $this->addSql('ALTER TABLE collection_items DROP CONSTRAINT FK_COLLECTION_ITEM_COLLECTION');
        $this->addSql('DROP INDEX idx_collection_items_collection_status');
        $this->addSql('ALTER TABLE collection_items DROP collection_id');
        $this->addSql('ALTER TABLE collections DROP CONSTRAINT FK_COLLECTION_OWNER');
        $this->addSql('DROP TABLE collections');
    }
}
