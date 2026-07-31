<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260731172941 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create the Catalog module schema';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE card_external_references (id UUID NOT NULL, corrected_data JSONB DEFAULT NULL, last_synced_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, source_name VARCHAR(100) NOT NULL, external_id VARCHAR(180) NOT NULL, raw_data JSONB NOT NULL, imported_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, catalog_card_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_FD66D6DF457E670C ON card_external_references (catalog_card_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_card_ext_ref_source ON card_external_references (source_name, external_id)');
        $this->addSql('CREATE TABLE card_series (id UUID NOT NULL, name VARCHAR(180) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_card_series_name ON card_series (name)');
        $this->addSql('CREATE TABLE card_sets (id UUID NOT NULL, name VARCHAR(180) NOT NULL, code VARCHAR(50) NOT NULL, language VARCHAR(10) NOT NULL, printed_total INT NOT NULL, total INT NOT NULL, status VARCHAR(20) NOT NULL, release_date DATE DEFAULT NULL, logo_url VARCHAR(1024) DEFAULT NULL, symbol_url VARCHAR(1024) DEFAULT NULL, description TEXT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, series_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_78BE47735278319C ON card_sets (series_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_card_sets_code ON card_sets (code)');
        $this->addSql('CREATE TABLE card_variants (id UUID NOT NULL, code VARCHAR(40) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, catalog_card_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_AA99D62457E670C ON card_variants (catalog_card_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_card_variants_card_code ON card_variants (catalog_card_id, code)');
        $this->addSql('CREATE TABLE catalog_cards (id UUID NOT NULL, number_sort_key VARCHAR(160) NOT NULL, name VARCHAR(180) NOT NULL, number VARCHAR(50) NOT NULL, rarity VARCHAR(100) NOT NULL, card_type VARCHAR(100) NOT NULL, total_number_in_set VARCHAR(50) DEFAULT NULL, primary_collectible VARCHAR(180) DEFAULT NULL, illustrator VARCHAR(180) DEFAULT NULL, image_url VARCHAR(1024) DEFAULT NULL, thumbnail_url VARCHAR(1024) DEFAULT NULL, release_date DATE DEFAULT NULL, metadata JSONB NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, set_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_BDD1394410FB0D18 ON catalog_cards (set_id)');
        $this->addSql('CREATE INDEX idx_catalog_cards_name ON catalog_cards (name)');
        $this->addSql('CREATE INDEX idx_catalog_cards_number_sort_key ON catalog_cards (number_sort_key)');
        $this->addSql('CREATE INDEX idx_catalog_cards_rarity ON catalog_cards (rarity)');
        $this->addSql('CREATE UNIQUE INDEX uniq_catalog_cards_set_number ON catalog_cards (set_id, number)');
        $this->addSql('ALTER TABLE card_external_references ADD CONSTRAINT FK_FD66D6DF457E670C FOREIGN KEY (catalog_card_id) REFERENCES catalog_cards (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE card_sets ADD CONSTRAINT FK_78BE47735278319C FOREIGN KEY (series_id) REFERENCES card_series (id) ON DELETE RESTRICT NOT DEFERRABLE');
        $this->addSql('ALTER TABLE card_variants ADD CONSTRAINT FK_AA99D62457E670C FOREIGN KEY (catalog_card_id) REFERENCES catalog_cards (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE catalog_cards ADD CONSTRAINT FK_BDD1394410FB0D18 FOREIGN KEY (set_id) REFERENCES card_sets (id) ON DELETE RESTRICT NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE card_external_references DROP CONSTRAINT FK_FD66D6DF457E670C');
        $this->addSql('ALTER TABLE card_sets DROP CONSTRAINT FK_78BE47735278319C');
        $this->addSql('ALTER TABLE card_variants DROP CONSTRAINT FK_AA99D62457E670C');
        $this->addSql('ALTER TABLE catalog_cards DROP CONSTRAINT FK_BDD1394410FB0D18');
        $this->addSql('DROP TABLE card_external_references');
        $this->addSql('DROP TABLE card_series');
        $this->addSql('DROP TABLE card_sets');
        $this->addSql('DROP TABLE card_variants');
        $this->addSql('DROP TABLE catalog_cards');
    }
}
