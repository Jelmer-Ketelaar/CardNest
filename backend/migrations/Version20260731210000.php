<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260731210000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create hierarchical storage locations and physical location history';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'CREATE TABLE storage_locations ('
            .'id UUID NOT NULL, '
            .'owner_id UUID NOT NULL, '
            .'parent_id UUID DEFAULT NULL, '
            .'name VARCHAR(180) NOT NULL, '
            .'type VARCHAR(30) NOT NULL, '
            .'created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, '
            .'updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, '
            .'PRIMARY KEY(id))',
        );
        $this->addSql('CREATE INDEX idx_storage_locations_owner_parent ON storage_locations (owner_id, parent_id)');
        $this->addSql(
            'ALTER TABLE storage_locations ADD CONSTRAINT FK_STORAGE_LOCATION_OWNER '
            .'FOREIGN KEY (owner_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE',
        );
        $this->addSql(
            'ALTER TABLE storage_locations ADD CONSTRAINT FK_STORAGE_LOCATION_PARENT '
            .'FOREIGN KEY (parent_id) REFERENCES storage_locations (id) '
            .'ON DELETE RESTRICT NOT DEFERRABLE INITIALLY IMMEDIATE',
        );

        $this->addSql(
            'CREATE TABLE collection_item_location_history ('
            .'id UUID NOT NULL, '
            .'collection_item_id UUID NOT NULL, '
            .'storage_location_id UUID NOT NULL, '
            .'placed_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, '
            .'removed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, '
            .'PRIMARY KEY(id))',
        );
        $this->addSql(
            'CREATE INDEX idx_location_history_item_time '
            .'ON collection_item_location_history (collection_item_id, placed_at)',
        );
        $this->addSql(
            'CREATE INDEX idx_location_history_location_current '
            .'ON collection_item_location_history (storage_location_id, removed_at)',
        );
        $this->addSql(
            'CREATE UNIQUE INDEX uniq_collection_item_current_location '
            .'ON collection_item_location_history (collection_item_id) WHERE removed_at IS NULL',
        );
        $this->addSql(
            'ALTER TABLE collection_item_location_history ADD CONSTRAINT FK_LOCATION_HISTORY_ITEM '
            .'FOREIGN KEY (collection_item_id) REFERENCES collection_items (id) '
            .'ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE',
        );
        $this->addSql(
            'ALTER TABLE collection_item_location_history ADD CONSTRAINT FK_LOCATION_HISTORY_LOCATION '
            .'FOREIGN KEY (storage_location_id) REFERENCES storage_locations (id) '
            .'ON DELETE RESTRICT NOT DEFERRABLE INITIALLY IMMEDIATE',
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE collection_item_location_history');
        $this->addSql('DROP TABLE storage_locations');
    }
}
