<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260731194000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Persist Catalog update timestamps';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'ALTER TABLE card_external_references '
            .'ADD updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL',
        );
        $this->addSql('ALTER TABLE card_external_references ALTER updated_at DROP DEFAULT');
        $this->addSql(
            'ALTER TABLE card_sets '
            .'ADD updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL',
        );
        $this->addSql('ALTER TABLE card_sets ALTER updated_at DROP DEFAULT');
        $this->addSql(
            'ALTER TABLE catalog_cards '
            .'ADD updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL',
        );
        $this->addSql('ALTER TABLE catalog_cards ALTER updated_at DROP DEFAULT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE card_external_references DROP updated_at');
        $this->addSql('ALTER TABLE card_sets DROP updated_at');
        $this->addSql('ALTER TABLE catalog_cards DROP updated_at');
    }
}
