<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260704130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds configurable production charges for cost calculation lots.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE cout_revient_charge_config (
                id INT AUTO_INCREMENT NOT NULL,
                created_by_id INT DEFAULT NULL,
                updated_by_id INT DEFAULT NULL,
                name VARCHAR(140) NOT NULL,
                category VARCHAR(40) DEFAULT 'autre' NOT NULL,
                calculation_unit VARCHAR(30) DEFAULT 'montant_direct' NOT NULL,
                unit_cost NUMERIC(12, 4) DEFAULT '0.0000' NOT NULL,
                description LONGTEXT DEFAULT NULL,
                is_active TINYINT(1) DEFAULT 1 NOT NULL,
                sort_order INT DEFAULT 0 NOT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME DEFAULT NULL,
                INDEX idx_cout_charge_config_active (is_active),
                INDEX idx_cout_charge_config_category (category),
                INDEX idx_cout_charge_config_unit (calculation_unit),
                INDEX idx_cout_charge_config_created_by (created_by_id),
                INDEX idx_cout_charge_config_updated_by (updated_by_id),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE cout_revient_charge_line (
                id INT AUTO_INCREMENT NOT NULL,
                cout_revient_id INT DEFAULT NULL,
                charge_config_id INT DEFAULT NULL,
                created_by_id INT DEFAULT NULL,
                updated_by_id INT DEFAULT NULL,
                name VARCHAR(140) NOT NULL,
                category VARCHAR(40) DEFAULT 'autre' NOT NULL,
                calculation_unit VARCHAR(30) DEFAULT 'montant_direct' NOT NULL,
                unit_cost NUMERIC(12, 4) DEFAULT '0.0000' NOT NULL,
                quantity NUMERIC(12, 3) DEFAULT '1.000' NOT NULL,
                total_amount NUMERIC(12, 2) DEFAULT '0.00' NOT NULL,
                note LONGTEXT DEFAULT NULL,
                sort_order INT DEFAULT 0 NOT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME DEFAULT NULL,
                INDEX idx_cout_charge_line_cout_revient (cout_revient_id),
                INDEX idx_cout_charge_line_config (charge_config_id),
                INDEX idx_cout_charge_line_category (category),
                INDEX idx_cout_charge_line_created_by (created_by_id),
                INDEX idx_cout_charge_line_updated_by (updated_by_id),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);

        $this->addSql('ALTER TABLE cout_revient_charge_config ADD CONSTRAINT FK_COUT_CHARGE_CONFIG_CREATED_BY FOREIGN KEY (created_by_id) REFERENCES app_user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE cout_revient_charge_config ADD CONSTRAINT FK_COUT_CHARGE_CONFIG_UPDATED_BY FOREIGN KEY (updated_by_id) REFERENCES app_user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE cout_revient_charge_line ADD CONSTRAINT FK_COUT_CHARGE_LINE_LOT FOREIGN KEY (cout_revient_id) REFERENCES cout_revient (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE cout_revient_charge_line ADD CONSTRAINT FK_COUT_CHARGE_LINE_CONFIG FOREIGN KEY (charge_config_id) REFERENCES cout_revient_charge_config (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE cout_revient_charge_line ADD CONSTRAINT FK_COUT_CHARGE_LINE_CREATED_BY FOREIGN KEY (created_by_id) REFERENCES app_user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE cout_revient_charge_line ADD CONSTRAINT FK_COUT_CHARGE_LINE_UPDATED_BY FOREIGN KEY (updated_by_id) REFERENCES app_user (id) ON DELETE SET NULL');

        $this->addSql(<<<'SQL'
            INSERT INTO cout_revient_charge_config
                (name, category, calculation_unit, unit_cost, description, is_active, sort_order, created_at, updated_at)
            VALUES
                ('Electricite usine', 'energie', 'mois', '0.0000', 'Charge mensuelle a repartir selon la production.', 1, 10, NOW(), NULL),
                ('Eau usine', 'eau', 'mois', '0.0000', 'Consommation eau mensuelle a affecter aux lots.', 1, 20, NOW(), NULL),
                ('Tunnel de congelation', 'froid', 'heure', '0.0000', 'Cout du tunnel quand il fonctionne.', 1, 30, NOW(), NULL),
                ('Stockage chambre froide', 'stockage', 'jour', '0.0000', 'Stockage froid par jour ou fraction de jour.', 1, 40, NOW(), NULL),
                ('Nettoyage production', 'nettoyage', 'lot', '0.0000', 'Nettoyage et sanitation affectes au lot.', 1, 50, NOW(), NULL),
                ('Maintenance froid', 'maintenance', 'mois', '0.0000', 'Maintenance des equipements froid a repartir.', 1, 60, NOW(), NULL),
                ('Manutention / logistique', 'logistique', 'lot', '0.0000', 'Charge logistique interne ou externe.', 1, 70, NOW(), NULL)
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE cout_revient_charge_line DROP FOREIGN KEY FK_COUT_CHARGE_LINE_UPDATED_BY');
        $this->addSql('ALTER TABLE cout_revient_charge_line DROP FOREIGN KEY FK_COUT_CHARGE_LINE_CREATED_BY');
        $this->addSql('ALTER TABLE cout_revient_charge_line DROP FOREIGN KEY FK_COUT_CHARGE_LINE_CONFIG');
        $this->addSql('ALTER TABLE cout_revient_charge_line DROP FOREIGN KEY FK_COUT_CHARGE_LINE_LOT');
        $this->addSql('ALTER TABLE cout_revient_charge_config DROP FOREIGN KEY FK_COUT_CHARGE_CONFIG_UPDATED_BY');
        $this->addSql('ALTER TABLE cout_revient_charge_config DROP FOREIGN KEY FK_COUT_CHARGE_CONFIG_CREATED_BY');
        $this->addSql('DROP TABLE cout_revient_charge_line');
        $this->addSql('DROP TABLE cout_revient_charge_config');
    }
}
