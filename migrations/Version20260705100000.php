<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260705100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds factory composition units and links them to production charge configuration.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE factory_unit (
                id INT AUTO_INCREMENT NOT NULL,
                created_by_id INT DEFAULT NULL,
                updated_by_id INT DEFAULT NULL,
                name VARCHAR(120) NOT NULL,
                code VARCHAR(60) NOT NULL,
                type VARCHAR(40) DEFAULT 'autre' NOT NULL,
                status VARCHAR(30) DEFAULT 'operationnel' NOT NULL,
                is_saturated TINYINT(1) DEFAULT 0 NOT NULL,
                is_active TINYINT(1) DEFAULT 1 NOT NULL,
                capacity_kg NUMERIC(12, 3) DEFAULT '0.000' NOT NULL,
                capacity_pallets INT DEFAULT 0 NOT NULL,
                capacity_boxes INT DEFAULT 0 NOT NULL,
                length_meters NUMERIC(8, 2) DEFAULT '0.00' NOT NULL,
                width_meters NUMERIC(8, 2) DEFAULT '0.00' NOT NULL,
                height_meters NUMERIC(8, 2) DEFAULT '0.00' NOT NULL,
                floor_level VARCHAR(80) DEFAULT NULL,
                location_label VARCHAR(150) DEFAULT NULL,
                target_temperature NUMERIC(6, 2) DEFAULT NULL,
                min_temperature NUMERIC(6, 2) DEFAULT NULL,
                max_temperature NUMERIC(6, 2) DEFAULT NULL,
                sort_order INT DEFAULT 0 NOT NULL,
                description LONGTEXT DEFAULT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME DEFAULT NULL,
                UNIQUE INDEX uniq_factory_unit_code (code),
                INDEX idx_factory_unit_type (type),
                INDEX idx_factory_unit_status (status),
                INDEX idx_factory_unit_active (is_active),
                INDEX idx_factory_unit_saturated (is_saturated),
                INDEX idx_factory_unit_created_by (created_by_id),
                INDEX idx_factory_unit_updated_by (updated_by_id),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql('ALTER TABLE factory_unit ADD CONSTRAINT FK_FACTORY_UNIT_CREATED_BY FOREIGN KEY (created_by_id) REFERENCES app_user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE factory_unit ADD CONSTRAINT FK_FACTORY_UNIT_UPDATED_BY FOREIGN KEY (updated_by_id) REFERENCES app_user (id) ON DELETE SET NULL');

        $this->addSql('ALTER TABLE cout_revient_charge_config ADD factory_unit_id INT DEFAULT NULL');
        $this->addSql('CREATE INDEX idx_cout_charge_config_factory_unit ON cout_revient_charge_config (factory_unit_id)');
        $this->addSql('ALTER TABLE cout_revient_charge_config ADD CONSTRAINT FK_COUT_CHARGE_FACTORY_UNIT FOREIGN KEY (factory_unit_id) REFERENCES factory_unit (id) ON DELETE SET NULL');

        $this->addSql(<<<'SQL'
            INSERT INTO app_module (name, slug, description, icon, route_name, is_active, created_at, updated_at)
            VALUES ('Composition usine', 'factory', 'Tunnels, chambres froides, zones de stockage et selections usine.', 'bi-building-gear', 'app_factory_unit_index', 1, NOW(), NULL)
            ON DUPLICATE KEY UPDATE
                name = VALUES(name),
                description = VALUES(description),
                icon = VALUES(icon),
                route_name = VALUES(route_name),
                is_active = 1,
                updated_at = NOW()
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE uma FROM user_module_access uma INNER JOIN app_module m ON uma.module_id = m.id WHERE m.slug = 'factory'");
        $this->addSql("DELETE FROM app_module WHERE slug = 'factory'");
        $this->addSql('ALTER TABLE cout_revient_charge_config DROP FOREIGN KEY FK_COUT_CHARGE_FACTORY_UNIT');
        $this->addSql('DROP INDEX idx_cout_charge_config_factory_unit ON cout_revient_charge_config');
        $this->addSql('ALTER TABLE cout_revient_charge_config DROP factory_unit_id');
        $this->addSql('DROP TABLE factory_unit');
    }
}
