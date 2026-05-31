<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260531092808 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE order_documents (id INT AUTO_INCREMENT NOT NULL, path VARCHAR(255) NOT NULL, original_name VARCHAR(255) NOT NULL, mime_type VARCHAR(100) NOT NULL, size_bytes INT NOT NULL, created_at DATETIME NOT NULL, order_id INT NOT NULL, uploaded_by_id INT NOT NULL, INDEX IDX_1E370970A2B28FE8 (uploaded_by_id), INDEX idx_orderdoc_order (order_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE order_documents ADD CONSTRAINT FK_1E3709708D9F6D38 FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE order_documents ADD CONSTRAINT FK_1E370970A2B28FE8 FOREIGN KEY (uploaded_by_id) REFERENCES users (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE order_documents DROP FOREIGN KEY FK_1E3709708D9F6D38');
        $this->addSql('ALTER TABLE order_documents DROP FOREIGN KEY FK_1E370970A2B28FE8');
        $this->addSql('DROP TABLE order_documents');
    }
}
