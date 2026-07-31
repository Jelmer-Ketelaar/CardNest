<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260801020000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Track one confirmed buyer collection import per order item';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE order_items ADD imported_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE order_items ADD imported_collection_item_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE order_items ADD CONSTRAINT chk_order_item_collection_import CHECK ((imported_collection_item_id IS NULL AND imported_at IS NULL) OR (imported_collection_item_id IS NOT NULL AND imported_at IS NOT NULL AND imported_at >= created_at))');
        $this->addSql('ALTER TABLE order_items ADD CONSTRAINT FK_62809DB0FCB827C3 FOREIGN KEY (imported_collection_item_id) REFERENCES collection_items (id) ON DELETE RESTRICT NOT DEFERRABLE');
        $this->addSql('CREATE INDEX IDX_62809DB0FCB827C3 ON order_items (imported_collection_item_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE order_items DROP CONSTRAINT FK_62809DB0FCB827C3');
        $this->addSql('DROP INDEX IDX_62809DB0FCB827C3');
        $this->addSql('ALTER TABLE order_items DROP imported_at');
        $this->addSql('ALTER TABLE order_items DROP imported_collection_item_id');
    }
}
