<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260618143000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds pending inventory requests for transport and physical count validation.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE inventory_request (
              id INT AUTO_INCREMENT NOT NULL,
              item_id INT NOT NULL,
              movement_id INT DEFAULT NULL,
              result_item_id INT DEFAULT NULL,
              from_site_id INT DEFAULT NULL,
              from_location_id INT DEFAULT NULL,
              to_site_id INT DEFAULT NULL,
              to_location_id INT DEFAULT NULL,
              validated_by_id INT DEFAULT NULL,
              canceled_by_id INT DEFAULT NULL,
              created_by_id INT DEFAULT NULL,
              updated_by_id INT DEFAULT NULL,
              request_type VARCHAR(30) NOT NULL,
              status VARCHAR(30) NOT NULL,
              requested_quantity INT NOT NULL,
              requested_logistics_status VARCHAR(30) DEFAULT NULL,
              counted_quantity INT DEFAULT NULL,
              reason VARCHAR(180) DEFAULT NULL,
              notes LONGTEXT DEFAULT NULL,
              resolution_note LONGTEXT DEFAULT NULL,
              validated_at DATETIME DEFAULT NULL,
              canceled_at DATETIME DEFAULT NULL,
              created_at DATETIME NOT NULL,
              updated_at DATETIME DEFAULT NULL,
              INDEX idx_inventory_request_item (item_id),
              INDEX idx_inventory_request_type (request_type),
              INDEX idx_inventory_request_status (status),
              INDEX idx_inventory_request_from_site (from_site_id),
              INDEX idx_inventory_request_from_location (from_location_id),
              INDEX idx_inventory_request_to_site (to_site_id),
              INDEX idx_inventory_request_to_location (to_location_id),
              INDEX idx_inventory_request_movement (movement_id),
              INDEX idx_inventory_request_result_item (result_item_id),
              INDEX idx_inventory_request_validated_by (validated_by_id),
              INDEX idx_inventory_request_canceled_by (canceled_by_id),
              INDEX idx_inventory_request_created_by (created_by_id),
              INDEX idx_inventory_request_updated_by (updated_by_id),
              PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 ENGINE = InnoDB
        SQL);

        $this->addSql('ALTER TABLE inventory_request ADD CONSTRAINT FK_INVENTORY_REQUEST_ITEM FOREIGN KEY (item_id) REFERENCES inventory_item (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE inventory_request ADD CONSTRAINT FK_INVENTORY_REQUEST_MOVEMENT FOREIGN KEY (movement_id) REFERENCES inventory_movement (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE inventory_request ADD CONSTRAINT FK_INVENTORY_REQUEST_RESULT_ITEM FOREIGN KEY (result_item_id) REFERENCES inventory_item (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE inventory_request ADD CONSTRAINT FK_INVENTORY_REQUEST_FROM_SITE FOREIGN KEY (from_site_id) REFERENCES inventory_site (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE inventory_request ADD CONSTRAINT FK_INVENTORY_REQUEST_FROM_LOCATION FOREIGN KEY (from_location_id) REFERENCES inventory_location (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE inventory_request ADD CONSTRAINT FK_INVENTORY_REQUEST_TO_SITE FOREIGN KEY (to_site_id) REFERENCES inventory_site (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE inventory_request ADD CONSTRAINT FK_INVENTORY_REQUEST_TO_LOCATION FOREIGN KEY (to_location_id) REFERENCES inventory_location (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE inventory_request ADD CONSTRAINT FK_INVENTORY_REQUEST_VALIDATED_BY FOREIGN KEY (validated_by_id) REFERENCES app_user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE inventory_request ADD CONSTRAINT FK_INVENTORY_REQUEST_CANCELED_BY FOREIGN KEY (canceled_by_id) REFERENCES app_user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE inventory_request ADD CONSTRAINT FK_INVENTORY_REQUEST_CREATED_BY FOREIGN KEY (created_by_id) REFERENCES app_user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE inventory_request ADD CONSTRAINT FK_INVENTORY_REQUEST_UPDATED_BY FOREIGN KEY (updated_by_id) REFERENCES app_user (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE inventory_request DROP FOREIGN KEY FK_INVENTORY_REQUEST_ITEM');
        $this->addSql('ALTER TABLE inventory_request DROP FOREIGN KEY FK_INVENTORY_REQUEST_MOVEMENT');
        $this->addSql('ALTER TABLE inventory_request DROP FOREIGN KEY FK_INVENTORY_REQUEST_RESULT_ITEM');
        $this->addSql('ALTER TABLE inventory_request DROP FOREIGN KEY FK_INVENTORY_REQUEST_FROM_SITE');
        $this->addSql('ALTER TABLE inventory_request DROP FOREIGN KEY FK_INVENTORY_REQUEST_FROM_LOCATION');
        $this->addSql('ALTER TABLE inventory_request DROP FOREIGN KEY FK_INVENTORY_REQUEST_TO_SITE');
        $this->addSql('ALTER TABLE inventory_request DROP FOREIGN KEY FK_INVENTORY_REQUEST_TO_LOCATION');
        $this->addSql('ALTER TABLE inventory_request DROP FOREIGN KEY FK_INVENTORY_REQUEST_VALIDATED_BY');
        $this->addSql('ALTER TABLE inventory_request DROP FOREIGN KEY FK_INVENTORY_REQUEST_CANCELED_BY');
        $this->addSql('ALTER TABLE inventory_request DROP FOREIGN KEY FK_INVENTORY_REQUEST_CREATED_BY');
        $this->addSql('ALTER TABLE inventory_request DROP FOREIGN KEY FK_INVENTORY_REQUEST_UPDATED_BY');
        $this->addSql('DROP TABLE inventory_request');
    }
}
