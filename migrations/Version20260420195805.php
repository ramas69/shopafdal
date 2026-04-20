<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260420195805 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE products (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(180) NOT NULL, slug VARCHAR(180) NOT NULL, description LONGTEXT DEFAULT NULL, category VARCHAR(80) DEFAULT NULL, material VARCHAR(80) DEFAULT NULL, base_price_cents INT DEFAULT 0 NOT NULL, images JSON NOT NULL, status VARCHAR(255) DEFAULT \'draft\' NOT NULL, published_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_B3BA5A5A989D9B62 (slug), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE reset_password_request (id INT AUTO_INCREMENT NOT NULL, selector VARCHAR(20) NOT NULL, hashed_token VARCHAR(100) NOT NULL, requested_at DATETIME NOT NULL, expires_at DATETIME NOT NULL, user_id INT NOT NULL, INDEX IDX_7CE748AA76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE users (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, full_name VARCHAR(120) NOT NULL, role VARCHAR(255) NOT NULL, password VARCHAR(255) NOT NULL, company_role VARCHAR(10) DEFAULT NULL, active TINYINT NOT NULL, created_at DATETIME NOT NULL, last_login_at DATETIME DEFAULT NULL, company_id INT DEFAULT NULL, INDEX IDX_1483A5E9979B1AD6 (company_id), UNIQUE INDEX uniq_user_email (email), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL, available_at DATETIME NOT NULL, delivered_at DATETIME DEFAULT NULL, INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 (queue_name, available_at, delivered_at, id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE reset_password_request ADD CONSTRAINT FK_7CE748AA76ED395 FOREIGN KEY (user_id) REFERENCES users (id)');
        $this->addSql('ALTER TABLE users ADD CONSTRAINT FK_1483A5E9979B1AD6 FOREIGN KEY (company_id) REFERENCES companies (id)');
        $this->addSql('ALTER TABLE antennas ADD CONSTRAINT FK_4FD1E364979B1AD6 FOREIGN KEY (company_id) REFERENCES companies (id)');
        $this->addSql('ALTER TABLE company_prices ADD CONSTRAINT FK_D954092C979B1AD6 FOREIGN KEY (company_id) REFERENCES companies (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE company_prices ADD CONSTRAINT FK_D954092C4584665A FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE favorites ADD CONSTRAINT FK_E46960F5A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE favorites ADD CONSTRAINT FK_E46960F54584665A FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE invitations ADD CONSTRAINT FK_232710AE979B1AD6 FOREIGN KEY (company_id) REFERENCES companies (id)');
        $this->addSql('ALTER TABLE marking_assets ADD CONSTRAINT FK_FC8E1224E415FB15 FOREIGN KEY (order_item_id) REFERENCES order_items (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE marking_assets ADD CONSTRAINT FK_FC8E1224A2B28FE8 FOREIGN KEY (uploaded_by_id) REFERENCES users (id)');
        $this->addSql('ALTER TABLE marking_assets ADD CONSTRAINT FK_FC8E1224FC6B21F1 FOREIGN KEY (reviewed_by_id) REFERENCES users (id)');
        $this->addSql('ALTER TABLE notifications ADD CONSTRAINT FK_6000B0D3E92F8F78 FOREIGN KEY (recipient_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE order_events ADD CONSTRAINT FK_9008479F8D9F6D38 FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE order_events ADD CONSTRAINT FK_9008479F10DAF24A FOREIGN KEY (actor_id) REFERENCES users (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE order_items ADD CONSTRAINT FK_62809DB08D9F6D38 FOREIGN KEY (order_id) REFERENCES orders (id)');
        $this->addSql('ALTER TABLE order_items ADD CONSTRAINT FK_62809DB03B69A9AF FOREIGN KEY (variant_id) REFERENCES product_variants (id)');
        $this->addSql('ALTER TABLE order_messages ADD CONSTRAINT FK_3AFAFC278D9F6D38 FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE order_messages ADD CONSTRAINT FK_3AFAFC27F675F31B FOREIGN KEY (author_id) REFERENCES users (id)');
        $this->addSql('ALTER TABLE orders ADD CONSTRAINT FK_E52FFDEE979B1AD6 FOREIGN KEY (company_id) REFERENCES companies (id)');
        $this->addSql('ALTER TABLE orders ADD CONSTRAINT FK_E52FFDEEB6FC8A64 FOREIGN KEY (antenna_id) REFERENCES antennas (id)');
        $this->addSql('ALTER TABLE orders ADD CONSTRAINT FK_E52FFDEEB03A8386 FOREIGN KEY (created_by_id) REFERENCES users (id)');
        $this->addSql('ALTER TABLE price_tiers ADD CONSTRAINT FK_3D8E33C24584665A FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE product_variants ADD CONSTRAINT FK_782839764584665A FOREIGN KEY (product_id) REFERENCES products (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE reset_password_request DROP FOREIGN KEY FK_7CE748AA76ED395');
        $this->addSql('ALTER TABLE users DROP FOREIGN KEY FK_1483A5E9979B1AD6');
        $this->addSql('DROP TABLE products');
        $this->addSql('DROP TABLE reset_password_request');
        $this->addSql('DROP TABLE users');
        $this->addSql('DROP TABLE messenger_messages');
        $this->addSql('ALTER TABLE antennas DROP FOREIGN KEY FK_4FD1E364979B1AD6');
        $this->addSql('ALTER TABLE company_prices DROP FOREIGN KEY FK_D954092C979B1AD6');
        $this->addSql('ALTER TABLE company_prices DROP FOREIGN KEY FK_D954092C4584665A');
        $this->addSql('ALTER TABLE favorites DROP FOREIGN KEY FK_E46960F5A76ED395');
        $this->addSql('ALTER TABLE favorites DROP FOREIGN KEY FK_E46960F54584665A');
        $this->addSql('ALTER TABLE invitations DROP FOREIGN KEY FK_232710AE979B1AD6');
        $this->addSql('ALTER TABLE marking_assets DROP FOREIGN KEY FK_FC8E1224E415FB15');
        $this->addSql('ALTER TABLE marking_assets DROP FOREIGN KEY FK_FC8E1224A2B28FE8');
        $this->addSql('ALTER TABLE marking_assets DROP FOREIGN KEY FK_FC8E1224FC6B21F1');
        $this->addSql('ALTER TABLE notifications DROP FOREIGN KEY FK_6000B0D3E92F8F78');
        $this->addSql('ALTER TABLE order_events DROP FOREIGN KEY FK_9008479F8D9F6D38');
        $this->addSql('ALTER TABLE order_events DROP FOREIGN KEY FK_9008479F10DAF24A');
        $this->addSql('ALTER TABLE order_items DROP FOREIGN KEY FK_62809DB08D9F6D38');
        $this->addSql('ALTER TABLE order_items DROP FOREIGN KEY FK_62809DB03B69A9AF');
        $this->addSql('ALTER TABLE order_messages DROP FOREIGN KEY FK_3AFAFC278D9F6D38');
        $this->addSql('ALTER TABLE order_messages DROP FOREIGN KEY FK_3AFAFC27F675F31B');
        $this->addSql('ALTER TABLE orders DROP FOREIGN KEY FK_E52FFDEE979B1AD6');
        $this->addSql('ALTER TABLE orders DROP FOREIGN KEY FK_E52FFDEEB6FC8A64');
        $this->addSql('ALTER TABLE orders DROP FOREIGN KEY FK_E52FFDEEB03A8386');
        $this->addSql('ALTER TABLE price_tiers DROP FOREIGN KEY FK_3D8E33C24584665A');
        $this->addSql('ALTER TABLE product_variants DROP FOREIGN KEY FK_782839764584665A');
    }
}
