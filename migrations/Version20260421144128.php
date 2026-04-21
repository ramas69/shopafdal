<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260421144128 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE product_company_access (product_id INT NOT NULL, company_id INT NOT NULL, INDEX IDX_689F310D4584665A (product_id), INDEX IDX_689F310D979B1AD6 (company_id), PRIMARY KEY (product_id, company_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE product_company_access ADD CONSTRAINT FK_689F310D4584665A FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE product_company_access ADD CONSTRAINT FK_689F310D979B1AD6 FOREIGN KEY (company_id) REFERENCES companies (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE product_company_access DROP FOREIGN KEY FK_689F310D4584665A');
        $this->addSql('ALTER TABLE product_company_access DROP FOREIGN KEY FK_689F310D979B1AD6');
        $this->addSql('DROP TABLE product_company_access');
    }
}
