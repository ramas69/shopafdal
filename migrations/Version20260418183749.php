<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260418183749 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE products ADD status VARCHAR(255) DEFAULT \'draft\' NOT NULL');
        $this->addSql('ALTER TABLE products ADD published_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        // Migrate existing active=true rows to status=published
        $this->addSql("UPDATE products SET status = 'published', published_at = created_at WHERE active = TRUE");
        $this->addSql('ALTER TABLE products DROP active');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE products ADD active BOOLEAN NOT NULL');
        $this->addSql('ALTER TABLE products DROP status');
        $this->addSql('ALTER TABLE products DROP published_at');
    }
}
