<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260802120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add a stable source key to catalog series imported across localized sources';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE card_series ADD source_key VARCHAR(100) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX uniq_card_series_source_key ON card_series (source_key)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX uniq_card_series_source_key');
        $this->addSql('ALTER TABLE card_series DROP source_key');
    }
}
