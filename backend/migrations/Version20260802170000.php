<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260802170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Resolve extensionless TCGdex card and set asset URLs';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            "UPDATE catalog_cards SET thumbnail_url = RTRIM(image_url, '/') || '/low.webp', "
            ."image_url = RTRIM(image_url, '/') || '/high.webp' "
            ."WHERE image_url LIKE 'https://assets.tcgdex.net/%' "
            ."AND image_url !~ '/(high|low)\\.(jpg|png|webp)$'",
        );
        $this->addSql(
            "UPDATE catalog_cards SET thumbnail_url = REGEXP_REPLACE(image_url, '/high\\.(jpg|png|webp)$', '/low.webp') "
            ."WHERE image_url LIKE 'https://assets.tcgdex.net/%/high.%' AND thumbnail_url IS NULL",
        );
        $this->addSql(
            "UPDATE card_sets SET logo_url = RTRIM(logo_url, '/') || '.webp' "
            ."WHERE logo_url LIKE 'https://assets.tcgdex.net/%' AND logo_url !~ '\\.(jpg|png|webp)$'",
        );
        $this->addSql(
            "UPDATE card_sets SET symbol_url = RTRIM(symbol_url, '/') || '.webp' "
            ."WHERE symbol_url LIKE 'https://assets.tcgdex.net/%' AND symbol_url !~ '\\.(jpg|png|webp)$'",
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql(
            "UPDATE catalog_cards SET image_url = REGEXP_REPLACE(image_url, '/high\\.webp$', ''), thumbnail_url = NULL "
            ."WHERE image_url LIKE 'https://assets.tcgdex.net/%/high.webp'",
        );
        $this->addSql(
            "UPDATE card_sets SET logo_url = REGEXP_REPLACE(logo_url, '\\.webp$', '') "
            ."WHERE logo_url LIKE 'https://assets.tcgdex.net/%.webp'",
        );
        $this->addSql(
            "UPDATE card_sets SET symbol_url = REGEXP_REPLACE(symbol_url, '\\.webp$', '') "
            ."WHERE symbol_url LIKE 'https://assets.tcgdex.net/%.webp'",
        );
    }
}
