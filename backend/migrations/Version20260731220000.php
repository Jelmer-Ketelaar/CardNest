<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260731220000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create binders, page surfaces, slots, placeholders and exclusive current slot placements';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'CREATE TABLE binders ('
            .'id UUID NOT NULL, '
            .'owner_id UUID NOT NULL, '
            .'parent_location_id UUID DEFAULT NULL, '
            .'name VARCHAR(180) NOT NULL, '
            .'grid_rows INT NOT NULL, '
            .'grid_columns INT NOT NULL, '
            .'page_count INT NOT NULL, '
            .'double_sided BOOLEAN NOT NULL, '
            .'layout_strategy VARCHAR(30) NOT NULL, '
            .'created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, '
            .'updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, '
            .'PRIMARY KEY(id), '
            .'CONSTRAINT chk_binder_rows CHECK (grid_rows BETWEEN 1 AND 6), '
            .'CONSTRAINT chk_binder_columns CHECK (grid_columns BETWEEN 1 AND 6), '
            .'CONSTRAINT chk_binder_pages CHECK (page_count BETWEEN 1 AND 100), '
            .'CONSTRAINT chk_binder_slots CHECK ('
            .'grid_rows * grid_columns * page_count * (CASE WHEN double_sided THEN 2 ELSE 1 END) <= 2000))',
        );
        $this->addSql('CREATE INDEX idx_binders_owner ON binders (owner_id)');
        $this->addSql('CREATE INDEX idx_binders_parent_location ON binders (parent_location_id)');
        $this->addSql(
            'ALTER TABLE binders ADD CONSTRAINT FK_BINDER_OWNER '
            .'FOREIGN KEY (owner_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE',
        );
        $this->addSql(
            'ALTER TABLE binders ADD CONSTRAINT FK_BINDER_PARENT_LOCATION '
            .'FOREIGN KEY (parent_location_id) REFERENCES storage_locations (id) '
            .'ON DELETE RESTRICT NOT DEFERRABLE INITIALLY IMMEDIATE',
        );

        $this->addSql(
            'CREATE TABLE binder_pages ('
            .'id UUID NOT NULL, '
            .'binder_id UUID NOT NULL, '
            .'page_number INT NOT NULL, '
            .'side VARCHAR(10) NOT NULL, '
            .'created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, '
            .'PRIMARY KEY(id))',
        );
        $this->addSql('CREATE UNIQUE INDEX uniq_binder_page_side ON binder_pages (binder_id, page_number, side)');
        $this->addSql(
            'ALTER TABLE binder_pages ADD CONSTRAINT FK_BINDER_PAGE_BINDER '
            .'FOREIGN KEY (binder_id) REFERENCES binders (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE',
        );

        $this->addSql(
            'CREATE TABLE binder_slots ('
            .'id UUID NOT NULL, '
            .'binder_page_id UUID NOT NULL, '
            .'reserved_catalog_card_id UUID DEFAULT NULL, '
            .'position INT NOT NULL, '
            .'placeholder_label VARCHAR(180) DEFAULT NULL, '
            .'created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, '
            .'updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, '
            .'PRIMARY KEY(id), '
            .'CONSTRAINT chk_binder_slot_position CHECK (position > 0))',
        );
        $this->addSql(
            'CREATE UNIQUE INDEX uniq_binder_slot_position ON binder_slots (binder_page_id, position)',
        );
        $this->addSql(
            'CREATE INDEX idx_binder_slots_reserved_card ON binder_slots (reserved_catalog_card_id)',
        );
        $this->addSql(
            'ALTER TABLE binder_slots ADD CONSTRAINT FK_BINDER_SLOT_PAGE '
            .'FOREIGN KEY (binder_page_id) REFERENCES binder_pages (id) '
            .'ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE',
        );
        $this->addSql(
            'ALTER TABLE binder_slots ADD CONSTRAINT FK_BINDER_SLOT_RESERVED_CARD '
            .'FOREIGN KEY (reserved_catalog_card_id) REFERENCES catalog_cards (id) '
            .'ON DELETE RESTRICT NOT DEFERRABLE INITIALLY IMMEDIATE',
        );

        $this->addSql('ALTER TABLE collection_item_location_history ALTER storage_location_id DROP NOT NULL');
        $this->addSql('ALTER TABLE collection_item_location_history ADD binder_slot_id UUID DEFAULT NULL');
        $this->addSql(
            'ALTER TABLE collection_item_location_history ADD CONSTRAINT chk_location_history_target CHECK ('
            .'(CASE WHEN storage_location_id IS NULL THEN 0 ELSE 1 END) '
            .'+ (CASE WHEN binder_slot_id IS NULL THEN 0 ELSE 1 END) = 1)',
        );
        $this->addSql(
            'CREATE INDEX idx_location_history_binder_slot_current '
            .'ON collection_item_location_history (binder_slot_id, removed_at)',
        );
        $this->addSql(
            'CREATE UNIQUE INDEX uniq_binder_slot_current_item '
            .'ON collection_item_location_history (binder_slot_id) '
            .'WHERE removed_at IS NULL AND binder_slot_id IS NOT NULL',
        );
        $this->addSql(
            'ALTER TABLE collection_item_location_history ADD CONSTRAINT FK_LOCATION_HISTORY_BINDER_SLOT '
            .'FOREIGN KEY (binder_slot_id) REFERENCES binder_slots (id) '
            .'ON DELETE RESTRICT NOT DEFERRABLE INITIALLY IMMEDIATE',
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DELETE FROM collection_item_location_history WHERE binder_slot_id IS NOT NULL');
        $this->addSql('DROP INDEX uniq_binder_slot_current_item');
        $this->addSql('DROP INDEX idx_location_history_binder_slot_current');
        $this->addSql('ALTER TABLE collection_item_location_history DROP CONSTRAINT FK_LOCATION_HISTORY_BINDER_SLOT');
        $this->addSql('ALTER TABLE collection_item_location_history DROP CONSTRAINT chk_location_history_target');
        $this->addSql('ALTER TABLE collection_item_location_history DROP binder_slot_id');
        $this->addSql('ALTER TABLE collection_item_location_history ALTER storage_location_id SET NOT NULL');
        $this->addSql('DROP TABLE binder_slots');
        $this->addSql('DROP TABLE binder_pages');
        $this->addSql('DROP TABLE binders');
    }
}
