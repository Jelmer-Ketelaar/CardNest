<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260801030000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add append-only transaction reviews and reputation scores';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE reviews (id UUID NOT NULL, comment TEXT DEFAULT NULL, overall_score INT NOT NULL, description_accuracy_score INT NOT NULL, packaging_score INT NOT NULL, communication_score INT NOT NULL, shipping_speed_score INT NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, order_id UUID NOT NULL, reviewer_id UUID NOT NULL, reviewed_user_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_6970EB0F8D9F6D38 ON reviews (order_id)');
        $this->addSql('CREATE INDEX IDX_6970EB0F70574616 ON reviews (reviewer_id)');
        $this->addSql('CREATE INDEX IDX_6970EB0FB9A2A077 ON reviews (reviewed_user_id)');
        $this->addSql('CREATE INDEX idx_reviews_reviewed_created ON reviews (reviewed_user_id, created_at)');
        $this->addSql('CREATE INDEX idx_reviews_reviewer_created ON reviews (reviewer_id, created_at)');
        $this->addSql('CREATE UNIQUE INDEX uniq_review_order_reviewer ON reviews (order_id, reviewer_id)');
        $this->addSql('ALTER TABLE reviews ADD CONSTRAINT chk_review_distinct_users CHECK (reviewer_id <> reviewed_user_id)');
        $this->addSql('ALTER TABLE reviews ADD CONSTRAINT chk_review_scores CHECK (overall_score BETWEEN 1 AND 5 AND description_accuracy_score BETWEEN 1 AND 5 AND packaging_score BETWEEN 1 AND 5 AND communication_score BETWEEN 1 AND 5 AND shipping_speed_score BETWEEN 1 AND 5)');
        $this->addSql('ALTER TABLE reviews ADD CONSTRAINT chk_review_comment_length CHECK (comment IS NULL OR char_length(comment) <= 2000)');
        $this->addSql('ALTER TABLE reviews ADD CONSTRAINT FK_6970EB0F8D9F6D38 FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE RESTRICT NOT DEFERRABLE');
        $this->addSql('ALTER TABLE reviews ADD CONSTRAINT FK_6970EB0F70574616 FOREIGN KEY (reviewer_id) REFERENCES users (id) ON DELETE RESTRICT NOT DEFERRABLE');
        $this->addSql('ALTER TABLE reviews ADD CONSTRAINT FK_6970EB0FB9A2A077 FOREIGN KEY (reviewed_user_id) REFERENCES users (id) ON DELETE RESTRICT NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE reviews DROP CONSTRAINT FK_6970EB0F8D9F6D38');
        $this->addSql('ALTER TABLE reviews DROP CONSTRAINT FK_6970EB0F70574616');
        $this->addSql('ALTER TABLE reviews DROP CONSTRAINT FK_6970EB0FB9A2A077');
        $this->addSql('DROP TABLE reviews');
    }
}
