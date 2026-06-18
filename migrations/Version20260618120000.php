<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260618120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds the autonomous inventory module with items, movements, locations, campaigns and private attachments.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE inventory_category (
              id INT AUTO_INCREMENT NOT NULL,
              name VARCHAR(160) NOT NULL,
              description LONGTEXT DEFAULT NULL,
              is_active TINYINT DEFAULT 1 NOT NULL,
              created_at DATETIME NOT NULL,
              updated_at DATETIME DEFAULT NULL,
              created_by_id INT DEFAULT NULL,
              updated_by_id INT DEFAULT NULL,
              UNIQUE INDEX uniq_inventory_category_name (name),
              INDEX idx_inventory_category_active (is_active),
              INDEX idx_inventory_category_created_by (created_by_id),
              INDEX idx_inventory_category_updated_by (updated_by_id),
              PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE inventory_site (
              id INT AUTO_INCREMENT NOT NULL,
              name VARCHAR(160) NOT NULL,
              description LONGTEXT DEFAULT NULL,
              is_active TINYINT DEFAULT 1 NOT NULL,
              created_at DATETIME NOT NULL,
              updated_at DATETIME DEFAULT NULL,
              created_by_id INT DEFAULT NULL,
              updated_by_id INT DEFAULT NULL,
              UNIQUE INDEX uniq_inventory_site_name (name),
              INDEX idx_inventory_site_active (is_active),
              INDEX idx_inventory_site_created_by (created_by_id),
              INDEX idx_inventory_site_updated_by (updated_by_id),
              PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE inventory_location (
              id INT AUTO_INCREMENT NOT NULL,
              site_id INT NOT NULL,
              name VARCHAR(160) NOT NULL,
              description LONGTEXT DEFAULT NULL,
              is_active TINYINT DEFAULT 1 NOT NULL,
              created_at DATETIME NOT NULL,
              updated_at DATETIME DEFAULT NULL,
              created_by_id INT DEFAULT NULL,
              updated_by_id INT DEFAULT NULL,
              UNIQUE INDEX uniq_inventory_location_site_name (site_id, name),
              INDEX idx_inventory_location_active (is_active),
              INDEX idx_inventory_location_site (site_id),
              INDEX idx_inventory_location_created_by (created_by_id),
              INDEX idx_inventory_location_updated_by (updated_by_id),
              PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE inventory_item (
              id INT AUTO_INCREMENT NOT NULL,
              category_id INT DEFAULT NULL,
              site_id INT DEFAULT NULL,
              location_id INT DEFAULT NULL,
              responsible_user_id INT DEFAULT NULL,
              created_by_id INT DEFAULT NULL,
              updated_by_id INT DEFAULT NULL,
              deleted_by_id INT DEFAULT NULL,
              reference VARCHAR(80) NOT NULL,
              name VARCHAR(180) NOT NULL,
              description LONGTEXT DEFAULT NULL,
              ownership_type VARCHAR(30) NOT NULL,
              owner_name VARCHAR(180) DEFAULT NULL,
              quantity INT NOT NULL,
              available_quantity INT NOT NULL,
              unit VARCHAR(40) NOT NULL,
              serial_number VARCHAR(120) DEFAULT NULL,
              brand VARCHAR(120) DEFAULT NULL,
              model VARCHAR(120) DEFAULT NULL,
              item_condition VARCHAR(30) NOT NULL,
              status VARCHAR(30) NOT NULL,
              acquisition_date DATE DEFAULT NULL,
              entry_date DATE DEFAULT NULL,
              acquisition_value NUMERIC(12, 2) DEFAULT NULL,
              notes LONGTEXT DEFAULT NULL,
              is_active TINYINT DEFAULT 1 NOT NULL,
              is_deleted TINYINT DEFAULT 0 NOT NULL,
              deleted_at DATETIME DEFAULT NULL,
              delete_reason LONGTEXT DEFAULT NULL,
              created_at DATETIME NOT NULL,
              updated_at DATETIME DEFAULT NULL,
              UNIQUE INDEX uniq_inventory_item_reference (reference),
              INDEX idx_inventory_item_status (status),
              INDEX idx_inventory_item_condition (item_condition),
              INDEX idx_inventory_item_category (category_id),
              INDEX idx_inventory_item_site (site_id),
              INDEX idx_inventory_item_location (location_id),
              INDEX idx_inventory_item_responsible (responsible_user_id),
              INDEX idx_inventory_item_active_deleted (is_active, is_deleted),
              INDEX idx_inventory_item_created_by (created_by_id),
              INDEX idx_inventory_item_updated_by (updated_by_id),
              INDEX idx_inventory_item_deleted_by (deleted_by_id),
              PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE inventory_attachment (
              id INT AUTO_INCREMENT NOT NULL,
              item_id INT NOT NULL,
              attachment_type VARCHAR(30) NOT NULL,
              original_file_name VARCHAR(255) NOT NULL,
              file_name VARCHAR(255) NOT NULL,
              mime_type VARCHAR(120) NOT NULL,
              file_size INT NOT NULL,
              is_active TINYINT DEFAULT 1 NOT NULL,
              created_at DATETIME NOT NULL,
              updated_at DATETIME DEFAULT NULL,
              created_by_id INT DEFAULT NULL,
              updated_by_id INT DEFAULT NULL,
              INDEX idx_inventory_attachment_item (item_id),
              INDEX idx_inventory_attachment_type (attachment_type),
              INDEX idx_inventory_attachment_created_by (created_by_id),
              INDEX idx_inventory_attachment_updated_by (updated_by_id),
              PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE inventory_movement (
              id INT AUTO_INCREMENT NOT NULL,
              item_id INT NOT NULL,
              from_site_id INT DEFAULT NULL,
              from_location_id INT DEFAULT NULL,
              to_site_id INT DEFAULT NULL,
              to_location_id INT DEFAULT NULL,
              responsible_user_id INT DEFAULT NULL,
              created_by_id INT DEFAULT NULL,
              updated_by_id INT DEFAULT NULL,
              movement_type VARCHAR(30) NOT NULL,
              quantity INT NOT NULL,
              movement_date DATETIME NOT NULL,
              reason VARCHAR(180) DEFAULT NULL,
              notes LONGTEXT DEFAULT NULL,
              INDEX idx_inventory_movement_item (item_id),
              INDEX idx_inventory_movement_type (movement_type),
              INDEX idx_inventory_movement_date (movement_date),
              INDEX idx_inventory_movement_from_site (from_site_id),
              INDEX idx_inventory_movement_from_location (from_location_id),
              INDEX idx_inventory_movement_to_site (to_site_id),
              INDEX idx_inventory_movement_to_location (to_location_id),
              INDEX idx_inventory_movement_responsible (responsible_user_id),
              INDEX idx_inventory_movement_created_by (created_by_id),
              INDEX idx_inventory_movement_updated_by (updated_by_id),
              PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE inventory_campaign (
              id INT AUTO_INCREMENT NOT NULL,
              site_id INT DEFAULT NULL,
              responsible_user_id INT DEFAULT NULL,
              created_by_id INT DEFAULT NULL,
              updated_by_id INT DEFAULT NULL,
              reference VARCHAR(80) NOT NULL,
              name VARCHAR(180) NOT NULL,
              start_date DATE NOT NULL,
              end_date DATE DEFAULT NULL,
              status VARCHAR(30) NOT NULL,
              participants JSON NOT NULL,
              notes LONGTEXT DEFAULT NULL,
              is_active TINYINT DEFAULT 1 NOT NULL,
              created_at DATETIME NOT NULL,
              updated_at DATETIME DEFAULT NULL,
              UNIQUE INDEX uniq_inventory_campaign_reference (reference),
              INDEX idx_inventory_campaign_status (status),
              INDEX idx_inventory_campaign_site (site_id),
              INDEX idx_inventory_campaign_responsible (responsible_user_id),
              INDEX idx_inventory_campaign_created_by (created_by_id),
              INDEX idx_inventory_campaign_updated_by (updated_by_id),
              PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE inventory_campaign_line (
              id INT AUTO_INCREMENT NOT NULL,
              campaign_id INT NOT NULL,
              item_id INT NOT NULL,
              checked_by_id INT DEFAULT NULL,
              created_by_id INT DEFAULT NULL,
              updated_by_id INT DEFAULT NULL,
              check_status VARCHAR(30) NOT NULL,
              theoretical_quantity INT NOT NULL,
              counted_quantity INT DEFAULT NULL,
              theoretical_location VARCHAR(255) DEFAULT NULL,
              counted_location VARCHAR(255) DEFAULT NULL,
              comment LONGTEXT DEFAULT NULL,
              checked_at DATETIME DEFAULT NULL,
              created_at DATETIME NOT NULL,
              updated_at DATETIME DEFAULT NULL,
              UNIQUE INDEX uniq_inventory_campaign_item (campaign_id, item_id),
              INDEX idx_inventory_campaign_line_campaign (campaign_id),
              INDEX idx_inventory_campaign_line_item (item_id),
              INDEX idx_inventory_campaign_line_status (check_status),
              INDEX idx_inventory_campaign_line_checked_by (checked_by_id),
              INDEX idx_inventory_campaign_line_created_by (created_by_id),
              INDEX idx_inventory_campaign_line_updated_by (updated_by_id),
              PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 ENGINE = InnoDB
        SQL);

        $this->addSql('ALTER TABLE inventory_category ADD CONSTRAINT FK_INVENTORY_CATEGORY_CREATED_BY FOREIGN KEY (created_by_id) REFERENCES app_user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE inventory_category ADD CONSTRAINT FK_INVENTORY_CATEGORY_UPDATED_BY FOREIGN KEY (updated_by_id) REFERENCES app_user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE inventory_site ADD CONSTRAINT FK_INVENTORY_SITE_CREATED_BY FOREIGN KEY (created_by_id) REFERENCES app_user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE inventory_site ADD CONSTRAINT FK_INVENTORY_SITE_UPDATED_BY FOREIGN KEY (updated_by_id) REFERENCES app_user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE inventory_location ADD CONSTRAINT FK_INVENTORY_LOCATION_SITE FOREIGN KEY (site_id) REFERENCES inventory_site (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE inventory_location ADD CONSTRAINT FK_INVENTORY_LOCATION_CREATED_BY FOREIGN KEY (created_by_id) REFERENCES app_user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE inventory_location ADD CONSTRAINT FK_INVENTORY_LOCATION_UPDATED_BY FOREIGN KEY (updated_by_id) REFERENCES app_user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE inventory_item ADD CONSTRAINT FK_INVENTORY_ITEM_CATEGORY FOREIGN KEY (category_id) REFERENCES inventory_category (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE inventory_item ADD CONSTRAINT FK_INVENTORY_ITEM_SITE FOREIGN KEY (site_id) REFERENCES inventory_site (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE inventory_item ADD CONSTRAINT FK_INVENTORY_ITEM_LOCATION FOREIGN KEY (location_id) REFERENCES inventory_location (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE inventory_item ADD CONSTRAINT FK_INVENTORY_ITEM_RESPONSIBLE FOREIGN KEY (responsible_user_id) REFERENCES app_user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE inventory_item ADD CONSTRAINT FK_INVENTORY_ITEM_CREATED_BY FOREIGN KEY (created_by_id) REFERENCES app_user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE inventory_item ADD CONSTRAINT FK_INVENTORY_ITEM_UPDATED_BY FOREIGN KEY (updated_by_id) REFERENCES app_user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE inventory_item ADD CONSTRAINT FK_INVENTORY_ITEM_DELETED_BY FOREIGN KEY (deleted_by_id) REFERENCES app_user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE inventory_attachment ADD CONSTRAINT FK_INVENTORY_ATTACHMENT_ITEM FOREIGN KEY (item_id) REFERENCES inventory_item (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE inventory_attachment ADD CONSTRAINT FK_INVENTORY_ATTACHMENT_CREATED_BY FOREIGN KEY (created_by_id) REFERENCES app_user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE inventory_attachment ADD CONSTRAINT FK_INVENTORY_ATTACHMENT_UPDATED_BY FOREIGN KEY (updated_by_id) REFERENCES app_user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE inventory_movement ADD CONSTRAINT FK_INVENTORY_MOVEMENT_ITEM FOREIGN KEY (item_id) REFERENCES inventory_item (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE inventory_movement ADD CONSTRAINT FK_INVENTORY_MOVEMENT_FROM_SITE FOREIGN KEY (from_site_id) REFERENCES inventory_site (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE inventory_movement ADD CONSTRAINT FK_INVENTORY_MOVEMENT_FROM_LOCATION FOREIGN KEY (from_location_id) REFERENCES inventory_location (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE inventory_movement ADD CONSTRAINT FK_INVENTORY_MOVEMENT_TO_SITE FOREIGN KEY (to_site_id) REFERENCES inventory_site (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE inventory_movement ADD CONSTRAINT FK_INVENTORY_MOVEMENT_TO_LOCATION FOREIGN KEY (to_location_id) REFERENCES inventory_location (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE inventory_movement ADD CONSTRAINT FK_INVENTORY_MOVEMENT_RESPONSIBLE FOREIGN KEY (responsible_user_id) REFERENCES app_user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE inventory_movement ADD CONSTRAINT FK_INVENTORY_MOVEMENT_CREATED_BY FOREIGN KEY (created_by_id) REFERENCES app_user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE inventory_movement ADD CONSTRAINT FK_INVENTORY_MOVEMENT_UPDATED_BY FOREIGN KEY (updated_by_id) REFERENCES app_user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE inventory_campaign ADD CONSTRAINT FK_INVENTORY_CAMPAIGN_SITE FOREIGN KEY (site_id) REFERENCES inventory_site (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE inventory_campaign ADD CONSTRAINT FK_INVENTORY_CAMPAIGN_RESPONSIBLE FOREIGN KEY (responsible_user_id) REFERENCES app_user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE inventory_campaign ADD CONSTRAINT FK_INVENTORY_CAMPAIGN_CREATED_BY FOREIGN KEY (created_by_id) REFERENCES app_user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE inventory_campaign ADD CONSTRAINT FK_INVENTORY_CAMPAIGN_UPDATED_BY FOREIGN KEY (updated_by_id) REFERENCES app_user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE inventory_campaign_line ADD CONSTRAINT FK_INVENTORY_CAMPAIGN_LINE_CAMPAIGN FOREIGN KEY (campaign_id) REFERENCES inventory_campaign (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE inventory_campaign_line ADD CONSTRAINT FK_INVENTORY_CAMPAIGN_LINE_ITEM FOREIGN KEY (item_id) REFERENCES inventory_item (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE inventory_campaign_line ADD CONSTRAINT FK_INVENTORY_CAMPAIGN_LINE_CHECKED_BY FOREIGN KEY (checked_by_id) REFERENCES app_user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE inventory_campaign_line ADD CONSTRAINT FK_INVENTORY_CAMPAIGN_LINE_CREATED_BY FOREIGN KEY (created_by_id) REFERENCES app_user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE inventory_campaign_line ADD CONSTRAINT FK_INVENTORY_CAMPAIGN_LINE_UPDATED_BY FOREIGN KEY (updated_by_id) REFERENCES app_user (id) ON DELETE SET NULL');
        $this->addSql(<<<'SQL'
            INSERT INTO app_module (name, slug, description, icon, route_name, is_active, created_at)
            SELECT 'Inventaire du materiel', 'inventory', 'Parc materiel, mouvements, inventaires physiques et pieces jointes privees.', 'bi-box-seam', 'app_inventory_dashboard', 1, NOW()
            WHERE NOT EXISTS (SELECT 1 FROM app_module WHERE slug = 'inventory')
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM app_module WHERE slug = 'inventory'");
        $this->addSql('ALTER TABLE inventory_campaign_line DROP FOREIGN KEY FK_INVENTORY_CAMPAIGN_LINE_CAMPAIGN');
        $this->addSql('ALTER TABLE inventory_campaign_line DROP FOREIGN KEY FK_INVENTORY_CAMPAIGN_LINE_ITEM');
        $this->addSql('ALTER TABLE inventory_campaign_line DROP FOREIGN KEY FK_INVENTORY_CAMPAIGN_LINE_CHECKED_BY');
        $this->addSql('ALTER TABLE inventory_campaign_line DROP FOREIGN KEY FK_INVENTORY_CAMPAIGN_LINE_CREATED_BY');
        $this->addSql('ALTER TABLE inventory_campaign_line DROP FOREIGN KEY FK_INVENTORY_CAMPAIGN_LINE_UPDATED_BY');
        $this->addSql('ALTER TABLE inventory_campaign DROP FOREIGN KEY FK_INVENTORY_CAMPAIGN_SITE');
        $this->addSql('ALTER TABLE inventory_campaign DROP FOREIGN KEY FK_INVENTORY_CAMPAIGN_RESPONSIBLE');
        $this->addSql('ALTER TABLE inventory_campaign DROP FOREIGN KEY FK_INVENTORY_CAMPAIGN_CREATED_BY');
        $this->addSql('ALTER TABLE inventory_campaign DROP FOREIGN KEY FK_INVENTORY_CAMPAIGN_UPDATED_BY');
        $this->addSql('ALTER TABLE inventory_movement DROP FOREIGN KEY FK_INVENTORY_MOVEMENT_ITEM');
        $this->addSql('ALTER TABLE inventory_movement DROP FOREIGN KEY FK_INVENTORY_MOVEMENT_FROM_SITE');
        $this->addSql('ALTER TABLE inventory_movement DROP FOREIGN KEY FK_INVENTORY_MOVEMENT_FROM_LOCATION');
        $this->addSql('ALTER TABLE inventory_movement DROP FOREIGN KEY FK_INVENTORY_MOVEMENT_TO_SITE');
        $this->addSql('ALTER TABLE inventory_movement DROP FOREIGN KEY FK_INVENTORY_MOVEMENT_TO_LOCATION');
        $this->addSql('ALTER TABLE inventory_movement DROP FOREIGN KEY FK_INVENTORY_MOVEMENT_RESPONSIBLE');
        $this->addSql('ALTER TABLE inventory_movement DROP FOREIGN KEY FK_INVENTORY_MOVEMENT_CREATED_BY');
        $this->addSql('ALTER TABLE inventory_movement DROP FOREIGN KEY FK_INVENTORY_MOVEMENT_UPDATED_BY');
        $this->addSql('ALTER TABLE inventory_attachment DROP FOREIGN KEY FK_INVENTORY_ATTACHMENT_ITEM');
        $this->addSql('ALTER TABLE inventory_attachment DROP FOREIGN KEY FK_INVENTORY_ATTACHMENT_CREATED_BY');
        $this->addSql('ALTER TABLE inventory_attachment DROP FOREIGN KEY FK_INVENTORY_ATTACHMENT_UPDATED_BY');
        $this->addSql('ALTER TABLE inventory_item DROP FOREIGN KEY FK_INVENTORY_ITEM_CATEGORY');
        $this->addSql('ALTER TABLE inventory_item DROP FOREIGN KEY FK_INVENTORY_ITEM_SITE');
        $this->addSql('ALTER TABLE inventory_item DROP FOREIGN KEY FK_INVENTORY_ITEM_LOCATION');
        $this->addSql('ALTER TABLE inventory_item DROP FOREIGN KEY FK_INVENTORY_ITEM_RESPONSIBLE');
        $this->addSql('ALTER TABLE inventory_item DROP FOREIGN KEY FK_INVENTORY_ITEM_CREATED_BY');
        $this->addSql('ALTER TABLE inventory_item DROP FOREIGN KEY FK_INVENTORY_ITEM_UPDATED_BY');
        $this->addSql('ALTER TABLE inventory_item DROP FOREIGN KEY FK_INVENTORY_ITEM_DELETED_BY');
        $this->addSql('ALTER TABLE inventory_location DROP FOREIGN KEY FK_INVENTORY_LOCATION_SITE');
        $this->addSql('ALTER TABLE inventory_location DROP FOREIGN KEY FK_INVENTORY_LOCATION_CREATED_BY');
        $this->addSql('ALTER TABLE inventory_location DROP FOREIGN KEY FK_INVENTORY_LOCATION_UPDATED_BY');
        $this->addSql('ALTER TABLE inventory_site DROP FOREIGN KEY FK_INVENTORY_SITE_CREATED_BY');
        $this->addSql('ALTER TABLE inventory_site DROP FOREIGN KEY FK_INVENTORY_SITE_UPDATED_BY');
        $this->addSql('ALTER TABLE inventory_category DROP FOREIGN KEY FK_INVENTORY_CATEGORY_CREATED_BY');
        $this->addSql('ALTER TABLE inventory_category DROP FOREIGN KEY FK_INVENTORY_CATEGORY_UPDATED_BY');
        $this->addSql('DROP TABLE inventory_campaign_line');
        $this->addSql('DROP TABLE inventory_campaign');
        $this->addSql('DROP TABLE inventory_movement');
        $this->addSql('DROP TABLE inventory_attachment');
        $this->addSql('DROP TABLE inventory_item');
        $this->addSql('DROP TABLE inventory_location');
        $this->addSql('DROP TABLE inventory_site');
        $this->addSql('DROP TABLE inventory_category');
    }
}
