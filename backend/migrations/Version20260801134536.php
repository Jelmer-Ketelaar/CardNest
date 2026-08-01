<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260801134536 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Persist hashed single-use password reset tokens';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE password_reset_tokens (id UUID NOT NULL, expires_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, token_hash VARCHAR(64) NOT NULL, user_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_3967A216B3BC57DA ON password_reset_tokens (token_hash)');
        $this->addSql('CREATE UNIQUE INDEX uniq_password_reset_user ON password_reset_tokens (user_id)');
        $this->addSql('ALTER TABLE password_reset_tokens ADD CONSTRAINT chk_password_reset_token_hash CHECK (char_length(token_hash) = 64)');
        $this->addSql('ALTER TABLE password_reset_tokens ADD CONSTRAINT chk_password_reset_expiry CHECK (expires_at > created_at)');
        $this->addSql('ALTER TABLE password_reset_tokens ADD CONSTRAINT FK_3967A216A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE password_reset_tokens DROP CONSTRAINT FK_3967A216A76ED395');
        $this->addSql('DROP TABLE password_reset_tokens');
    }
}
