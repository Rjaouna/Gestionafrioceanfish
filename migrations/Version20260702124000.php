<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260702124000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds consumable stock management with thresholds, movements and inventory counts.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE consumable_stock_item (
              id INT AUTO_INCREMENT NOT NULL,
              created_by_id INT DEFAULT NULL,
              updated_by_id INT DEFAULT NULL,
              reference VARCHAR(80) NOT NULL,
              name VARCHAR(180) NOT NULL,
              category VARCHAR(120) DEFAULT NULL,
              unit VARCHAR(40) NOT NULL DEFAULT 'piece',
              quantity NUMERIC(12, 2) NOT NULL DEFAULT '0.00',
              minimum_quantity NUMERIC(12, 2) NOT NULL DEFAULT '0.00',
              storage_location VARCHAR(180) DEFAULT NULL,
              preferred_supplier VARCHAR(180) DEFAULT NULL,
              last_inventory_at DATETIME DEFAULT NULL,
              notes LONGTEXT DEFAULT NULL,
              is_active TINYINT(1) NOT NULL DEFAULT 1,
              created_at DATETIME NOT NULL,
              updated_at DATETIME DEFAULT NULL,
              UNIQUE INDEX uniq_consumable_stock_item_reference (reference),
              INDEX idx_consumable_stock_item_name (name),
              INDEX idx_consumable_stock_item_category (category),
              INDEX idx_consumable_stock_item_active (is_active),
              INDEX idx_consumable_stock_item_created_by (created_by_id),
              INDEX idx_consumable_stock_item_updated_by (updated_by_id),
              PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE consumable_stock_movement (
              id INT AUTO_INCREMENT NOT NULL,
              item_id INT NOT NULL,
              performed_by_id INT DEFAULT NULL,
              created_by_id INT DEFAULT NULL,
              updated_by_id INT DEFAULT NULL,
              movement_type VARCHAR(30) NOT NULL,
              quantity NUMERIC(12, 2) NOT NULL,
              previous_quantity NUMERIC(12, 2) NOT NULL,
              new_quantity NUMERIC(12, 2) NOT NULL,
              unit_cost NUMERIC(12, 2) DEFAULT NULL,
              movement_date DATETIME NOT NULL,
              supplier VARCHAR(180) DEFAULT NULL,
              recipient VARCHAR(180) DEFAULT NULL,
              reason LONGTEXT DEFAULT NULL,
              created_at DATETIME NOT NULL,
              updated_at DATETIME DEFAULT NULL,
              INDEX idx_consumable_stock_movement_item (item_id),
              INDEX idx_consumable_stock_movement_type (movement_type),
              INDEX idx_consumable_stock_movement_date (movement_date),
              INDEX idx_consumable_stock_movement_performed_by (performed_by_id),
              INDEX idx_consumable_stock_movement_created_by (created_by_id),
              INDEX idx_consumable_stock_movement_updated_by (updated_by_id),
              PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);

        $this->addSql('ALTER TABLE consumable_stock_item ADD CONSTRAINT FK_CONSUMABLE_STOCK_ITEM_CREATED_BY FOREIGN KEY (created_by_id) REFERENCES app_user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE consumable_stock_item ADD CONSTRAINT FK_CONSUMABLE_STOCK_ITEM_UPDATED_BY FOREIGN KEY (updated_by_id) REFERENCES app_user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE consumable_stock_movement ADD CONSTRAINT FK_CONSUMABLE_STOCK_MOVEMENT_ITEM FOREIGN KEY (item_id) REFERENCES consumable_stock_item (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE consumable_stock_movement ADD CONSTRAINT FK_CONSUMABLE_STOCK_MOVEMENT_PERFORMED_BY FOREIGN KEY (performed_by_id) REFERENCES app_user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE consumable_stock_movement ADD CONSTRAINT FK_CONSUMABLE_STOCK_MOVEMENT_CREATED_BY FOREIGN KEY (created_by_id) REFERENCES app_user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE consumable_stock_movement ADD CONSTRAINT FK_CONSUMABLE_STOCK_MOVEMENT_UPDATED_BY FOREIGN KEY (updated_by_id) REFERENCES app_user (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE consumable_stock_movement DROP FOREIGN KEY FK_CONSUMABLE_STOCK_MOVEMENT_ITEM');
        $this->addSql('ALTER TABLE consumable_stock_movement DROP FOREIGN KEY FK_CONSUMABLE_STOCK_MOVEMENT_PERFORMED_BY');
        $this->addSql('ALTER TABLE consumable_stock_movement DROP FOREIGN KEY FK_CONSUMABLE_STOCK_MOVEMENT_CREATED_BY');
        $this->addSql('ALTER TABLE consumable_stock_movement DROP FOREIGN KEY FK_CONSUMABLE_STOCK_MOVEMENT_UPDATED_BY');
        $this->addSql('ALTER TABLE consumable_stock_item DROP FOREIGN KEY FK_CONSUMABLE_STOCK_ITEM_CREATED_BY');
        $this->addSql('ALTER TABLE consumable_stock_item DROP FOREIGN KEY FK_CONSUMABLE_STOCK_ITEM_UPDATED_BY');
        $this->addSql('DROP TABLE consumable_stock_movement');
        $this->addSql('DROP TABLE consumable_stock_item');
    }
}
