<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260802180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Normalize catalog illustrators into relational artists';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE artists (id UUID NOT NULL, canonical_name VARCHAR(180) NOT NULL, normalized_name VARCHAR(180) NOT NULL, slug VARCHAR(220) NOT NULL, biography TEXT DEFAULT NULL, website_url VARCHAR(1024) DEFAULT NULL, profile_image_url VARCHAR(1024) DEFAULT NULL, external_references JSONB NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_artists_normalized_name ON artists (normalized_name)');
        $this->addSql('CREATE UNIQUE INDEX uniq_artists_slug ON artists (slug)');
        $this->addSql('CREATE INDEX idx_artists_normalized_name ON artists (normalized_name)');
        $this->addSql('CREATE TABLE catalog_card_artists (id UUID NOT NULL, catalog_card_id UUID NOT NULL, artist_id UUID NOT NULL, role VARCHAR(40) NOT NULL, display_order INT NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_catalog_card_artist ON catalog_card_artists (catalog_card_id, artist_id)');
        $this->addSql('CREATE INDEX idx_catalog_card_artists_artist ON catalog_card_artists (artist_id)');
        $this->addSql('CREATE INDEX idx_catalog_card_artists_card ON catalog_card_artists (catalog_card_id)');
        $this->addSql('ALTER TABLE catalog_card_artists ADD CONSTRAINT FK_CATALOG_CARD_ARTISTS_CARD FOREIGN KEY (catalog_card_id) REFERENCES catalog_cards (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE catalog_card_artists ADD CONSTRAINT FK_CATALOG_CARD_ARTISTS_ARTIST FOREIGN KEY (artist_id) REFERENCES artists (id) ON DELETE RESTRICT NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql("INSERT INTO artists (id, canonical_name, normalized_name, slug, external_references, created_at, updated_at) SELECT gen_random_uuid(), MIN(trimmed_name), normalized_name, regexp_replace(normalized_name, '[^a-z0-9]+', '-', 'g') || '-' || substr(md5(normalized_name), 1, 8), '{}'::jsonb, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP FROM (SELECT regexp_replace(trim(illustrator), '\\s+', ' ', 'g') AS trimmed_name, lower(regexp_replace(trim(illustrator), '\\s+', ' ', 'g')) AS normalized_name FROM catalog_cards WHERE illustrator IS NOT NULL AND trim(illustrator) <> '') source GROUP BY normalized_name");
        $this->addSql("INSERT INTO catalog_card_artists (id, catalog_card_id, artist_id, role, display_order, created_at) SELECT gen_random_uuid(), card.id, artist.id, 'primary_illustrator', 0, CURRENT_TIMESTAMP FROM catalog_cards card JOIN artists artist ON artist.normalized_name = lower(regexp_replace(trim(card.illustrator), '\\s+', ' ', 'g')) WHERE card.illustrator IS NOT NULL AND trim(card.illustrator) <> ''");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE catalog_card_artists DROP CONSTRAINT FK_CATALOG_CARD_ARTISTS_CARD');
        $this->addSql('ALTER TABLE catalog_card_artists DROP CONSTRAINT FK_CATALOG_CARD_ARTISTS_ARTIST');
        $this->addSql('DROP TABLE catalog_card_artists');
        $this->addSql('DROP TABLE artists');
    }
}
