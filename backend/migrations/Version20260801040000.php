<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260801040000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Persist single-use email verification tokens without storing plaintext secrets';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE email_verification_tokens (id UUID NOT NULL, expires_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, token_hash VARCHAR(64) NOT NULL, user_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_C81CA2ACB3BC57DA ON email_verification_tokens (token_hash)');
        $this->addSql('CREATE UNIQUE INDEX uniq_email_verification_user ON email_verification_tokens (user_id)');
        $this->addSql('ALTER TABLE email_verification_tokens ADD CONSTRAINT chk_email_verification_token_hash CHECK (char_length(token_hash) = 64)');
        $this->addSql('ALTER TABLE email_verification_tokens ADD CONSTRAINT chk_email_verification_expiry CHECK (expires_at > created_at)');
        $this->addSql('ALTER TABLE email_verification_tokens ADD CONSTRAINT FK_C81CA2ACA76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE email_verification_tokens DROP CONSTRAINT FK_C81CA2ACA76ED395');
        $this->addSql('DROP TABLE email_verification_tokens');
    }
}
