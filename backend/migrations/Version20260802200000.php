<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260802200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Clear contradictory placeholders from occupied binder slots';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'UPDATE binder_slots slot '
            .'SET reserved_catalog_card_id = NULL, placeholder_label = NULL, updated_at = CURRENT_TIMESTAMP '
            .'WHERE (slot.reserved_catalog_card_id IS NOT NULL OR slot.placeholder_label IS NOT NULL) '
            .'AND EXISTS ('
            .'SELECT 1 FROM collection_item_location_history history '
            .'WHERE history.binder_slot_id = slot.id AND history.removed_at IS NULL)',
        );
    }

    public function down(Schema $schema): void
    {
        // Consumed placeholders cannot be reconstructed from placement history.
    }
}
