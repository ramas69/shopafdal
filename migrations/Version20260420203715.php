<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260420203715 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE antennas (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(180) NOT NULL, address_line LONGTEXT NOT NULL, postal_code VARCHAR(20) NOT NULL, city VARCHAR(120) NOT NULL, country VARCHAR(2) DEFAULT \'FR\' NOT NULL, phone VARCHAR(30) DEFAULT NULL, notes LONGTEXT DEFAULT NULL, contact_name VARCHAR(120) DEFAULT NULL, contact_email VARCHAR(180) DEFAULT NULL, contact_phone VARCHAR(30) DEFAULT NULL, created_at DATETIME NOT NULL, company_id INT NOT NULL, INDEX IDX_4FD1E364979B1AD6 (company_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE companies (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(180) NOT NULL, slug VARCHAR(180) NOT NULL, siret VARCHAR(50) DEFAULT NULL, created_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_8244AA3A989D9B62 (slug), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE company_prices (id INT AUTO_INCREMENT NOT NULL, unit_price_cents INT NOT NULL, company_id INT NOT NULL, product_id INT NOT NULL, INDEX IDX_D954092C979B1AD6 (company_id), INDEX IDX_D954092C4584665A (product_id), UNIQUE INDEX uniq_company_product (company_id, product_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE favorites (id INT AUTO_INCREMENT NOT NULL, created_at DATETIME NOT NULL, user_id INT NOT NULL, product_id INT NOT NULL, INDEX IDX_E46960F5A76ED395 (user_id), INDEX IDX_E46960F54584665A (product_id), UNIQUE INDEX uniq_favorite_user_product (user_id, product_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE invitations (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, target_role VARCHAR(30) DEFAULT \'ROLE_CLIENT_MANAGER\' NOT NULL, token VARCHAR(64) NOT NULL, expires_at DATETIME NOT NULL, accepted_at DATETIME DEFAULT NULL, revoked_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, company_role VARCHAR(10) DEFAULT \'member\' NOT NULL, company_id INT DEFAULT NULL, INDEX IDX_232710AE979B1AD6 (company_id), UNIQUE INDEX uniq_invitation_token (token), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE marking_assets (id INT AUTO_INCREMENT NOT NULL, logo_path VARCHAR(255) NOT NULL, status VARCHAR(20) DEFAULT \'pending\' NOT NULL, feedback LONGTEXT DEFAULT NULL, version INT DEFAULT 1 NOT NULL, created_at DATETIME NOT NULL, reviewed_at DATETIME DEFAULT NULL, order_item_id INT NOT NULL, uploaded_by_id INT NOT NULL, reviewed_by_id INT DEFAULT NULL, INDEX IDX_FC8E1224E415FB15 (order_item_id), INDEX IDX_FC8E1224A2B28FE8 (uploaded_by_id), INDEX IDX_FC8E1224FC6B21F1 (reviewed_by_id), INDEX idx_marking_item_version (order_item_id, version), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE notifications (id INT AUTO_INCREMENT NOT NULL, type VARCHAR(20) DEFAULT \'info\' NOT NULL, title VARCHAR(180) NOT NULL, message LONGTEXT DEFAULT NULL, link_url VARCHAR(255) DEFAULT NULL, read_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, recipient_id INT NOT NULL, INDEX IDX_6000B0D3E92F8F78 (recipient_id), INDEX idx_notif_recipient_read (recipient_id, read_at), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE order_events (id INT AUTO_INCREMENT NOT NULL, type VARCHAR(30) NOT NULL, summary VARCHAR(255) NOT NULL, data JSON DEFAULT NULL, created_at DATETIME NOT NULL, order_id INT NOT NULL, actor_id INT DEFAULT NULL, INDEX IDX_9008479F8D9F6D38 (order_id), INDEX IDX_9008479F10DAF24A (actor_id), INDEX idx_oe_order_created (order_id, created_at), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE order_items (id INT AUTO_INCREMENT NOT NULL, quantity INT NOT NULL, unit_price_cents INT DEFAULT 0 NOT NULL, marking JSON DEFAULT NULL, order_id INT NOT NULL, variant_id INT NOT NULL, INDEX IDX_62809DB08D9F6D38 (order_id), INDEX IDX_62809DB03B69A9AF (variant_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE order_messages (id INT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, created_at DATETIME NOT NULL, read_by_client_at DATETIME DEFAULT NULL, read_by_admin_at DATETIME DEFAULT NULL, order_id INT NOT NULL, author_id INT NOT NULL, INDEX IDX_3AFAFC278D9F6D38 (order_id), INDEX IDX_3AFAFC27F675F31B (author_id), INDEX idx_om_order_created (order_id, created_at), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE orders (id INT AUTO_INCREMENT NOT NULL, reference VARCHAR(20) NOT NULL, status VARCHAR(255) NOT NULL, notes LONGTEXT DEFAULT NULL, admin_notes LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, placed_at DATETIME DEFAULT NULL, confirmed_at DATETIME DEFAULT NULL, in_production_at DATETIME DEFAULT NULL, shipped_at DATETIME DEFAULT NULL, delivered_at DATETIME DEFAULT NULL, cancelled_at DATETIME DEFAULT NULL, updated_at DATETIME DEFAULT NULL, carrier VARCHAR(40) DEFAULT NULL, tracking_number VARCHAR(80) DEFAULT NULL, estimated_delivery_at DATETIME DEFAULT NULL, company_id INT NOT NULL, antenna_id INT NOT NULL, created_by_id INT NOT NULL, UNIQUE INDEX UNIQ_E52FFDEEAEA34913 (reference), INDEX IDX_E52FFDEE979B1AD6 (company_id), INDEX IDX_E52FFDEEB6FC8A64 (antenna_id), INDEX IDX_E52FFDEEB03A8386 (created_by_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE price_tiers (id INT AUTO_INCREMENT NOT NULL, min_qty INT NOT NULL, unit_price_cents INT NOT NULL, product_id INT NOT NULL, INDEX IDX_3D8E33C24584665A (product_id), UNIQUE INDEX uniq_tier_product_minqty (product_id, min_qty), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE product_variants (id INT AUTO_INCREMENT NOT NULL, size VARCHAR(20) NOT NULL, color VARCHAR(40) NOT NULL, color_hex VARCHAR(7) DEFAULT NULL, sku VARCHAR(80) NOT NULL, stock INT DEFAULT NULL, product_id INT NOT NULL, INDEX IDX_782839764584665A (product_id), UNIQUE INDEX uniq_variant_sku (sku), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE products (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(180) NOT NULL, slug VARCHAR(180) NOT NULL, description LONGTEXT DEFAULT NULL, category VARCHAR(80) DEFAULT NULL, material VARCHAR(80) DEFAULT NULL, base_price_cents INT DEFAULT 0 NOT NULL, images JSON NOT NULL, status VARCHAR(255) DEFAULT \'draft\' NOT NULL, published_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_B3BA5A5A989D9B62 (slug), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE reset_password_request (id INT AUTO_INCREMENT NOT NULL, selector VARCHAR(20) NOT NULL, hashed_token VARCHAR(100) NOT NULL, requested_at DATETIME NOT NULL, expires_at DATETIME NOT NULL, user_id INT NOT NULL, INDEX IDX_7CE748AA76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE users (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, full_name VARCHAR(120) NOT NULL, role VARCHAR(255) NOT NULL, password VARCHAR(255) NOT NULL, company_role VARCHAR(10) DEFAULT NULL, active TINYINT NOT NULL, created_at DATETIME NOT NULL, last_login_at DATETIME DEFAULT NULL, company_id INT DEFAULT NULL, INDEX IDX_1483A5E9979B1AD6 (company_id), UNIQUE INDEX uniq_user_email (email), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL, available_at DATETIME NOT NULL, delivered_at DATETIME DEFAULT NULL, INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 (queue_name, available_at, delivered_at, id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
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
        $this->addSql('ALTER TABLE reset_password_request ADD CONSTRAINT FK_7CE748AA76ED395 FOREIGN KEY (user_id) REFERENCES users (id)');
        $this->addSql('ALTER TABLE users ADD CONSTRAINT FK_1483A5E9979B1AD6 FOREIGN KEY (company_id) REFERENCES companies (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
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
        $this->addSql('ALTER TABLE reset_password_request DROP FOREIGN KEY FK_7CE748AA76ED395');
        $this->addSql('ALTER TABLE users DROP FOREIGN KEY FK_1483A5E9979B1AD6');
        $this->addSql('DROP TABLE antennas');
        $this->addSql('DROP TABLE companies');
        $this->addSql('DROP TABLE company_prices');
        $this->addSql('DROP TABLE favorites');
        $this->addSql('DROP TABLE invitations');
        $this->addSql('DROP TABLE marking_assets');
        $this->addSql('DROP TABLE notifications');
        $this->addSql('DROP TABLE order_events');
        $this->addSql('DROP TABLE order_items');
        $this->addSql('DROP TABLE order_messages');
        $this->addSql('DROP TABLE orders');
        $this->addSql('DROP TABLE price_tiers');
        $this->addSql('DROP TABLE product_variants');
        $this->addSql('DROP TABLE products');
        $this->addSql('DROP TABLE reset_password_request');
        $this->addSql('DROP TABLE users');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
