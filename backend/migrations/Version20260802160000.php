<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260802160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Store daily, source-specific external card price snapshots';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'CREATE TABLE price_snapshots ('
            .'id UUID NOT NULL, catalog_card_id UUID NOT NULL, source VARCHAR(50) NOT NULL, '
            .'market VARCHAR(50) NOT NULL, variant VARCHAR(30) NOT NULL, currency VARCHAR(3) NOT NULL, '
            .'lowest_amount INT DEFAULT NULL, average_amount INT DEFAULT NULL, trend_amount INT DEFAULT NULL, '
            .'average_one_day_amount INT DEFAULT NULL, average_seven_days_amount INT DEFAULT NULL, '
            .'average_thirty_days_amount INT DEFAULT NULL, source_updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, '
            .'observed_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, observed_on DATE NOT NULL, '
            .'created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, '
            .'PRIMARY KEY (id))',
        );
        $this->addSql('CREATE INDEX idx_price_snapshots_card_observed ON price_snapshots (catalog_card_id, observed_on)');
        $this->addSql(
            'CREATE UNIQUE INDEX uniq_price_snapshot_card_source_variant_day '
            .'ON price_snapshots (catalog_card_id, source, market, variant, observed_on)',
        );
        $this->addSql(
            'ALTER TABLE price_snapshots ADD CONSTRAINT FK_PRICE_SNAPSHOT_CARD '
            .'FOREIGN KEY (catalog_card_id) REFERENCES catalog_cards (id) ON DELETE CASCADE NOT DEFERRABLE',
        );
        $this->addSql(
            'ALTER TABLE price_snapshots ADD CONSTRAINT chk_price_snapshot_variant '
            ."CHECK (variant IN ('standard', 'foil', 'unavailable'))",
        );
        $this->addSql(
            'ALTER TABLE price_snapshots ADD CONSTRAINT chk_price_snapshot_currency '
            ."CHECK (currency ~ '^[A-Z]{3}$')",
        );
        $this->addSql(
            'ALTER TABLE price_snapshots ADD CONSTRAINT chk_price_snapshot_non_negative '
            .'CHECK ((lowest_amount IS NULL OR lowest_amount >= 0) '
            .'AND (average_amount IS NULL OR average_amount >= 0) '
            .'AND (trend_amount IS NULL OR trend_amount >= 0) '
            .'AND (average_one_day_amount IS NULL OR average_one_day_amount >= 0) '
            .'AND (average_seven_days_amount IS NULL OR average_seven_days_amount >= 0) '
            .'AND (average_thirty_days_amount IS NULL OR average_thirty_days_amount >= 0))',
        );
        $this->addSql(
            'ALTER TABLE price_snapshots ADD CONSTRAINT chk_price_snapshot_availability CHECK ('
            ."(variant = 'unavailable' AND lowest_amount IS NULL AND average_amount IS NULL "
            .'AND trend_amount IS NULL AND average_one_day_amount IS NULL '
            .'AND average_seven_days_amount IS NULL AND average_thirty_days_amount IS NULL) OR '
            ."(variant <> 'unavailable' AND (lowest_amount IS NOT NULL OR average_amount IS NOT NULL "
            .'OR trend_amount IS NOT NULL OR average_one_day_amount IS NOT NULL '
            .'OR average_seven_days_amount IS NOT NULL OR average_thirty_days_amount IS NOT NULL)))',
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE price_snapshots DROP CONSTRAINT FK_PRICE_SNAPSHOT_CARD');
        $this->addSql('DROP TABLE price_snapshots');
    }
}
