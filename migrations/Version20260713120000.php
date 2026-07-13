<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260713120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds daily production cost module tables.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE daily_production_cost (
            id INT AUTO_INCREMENT NOT NULL,
            created_by_id INT DEFAULT NULL,
            updated_by_id INT DEFAULT NULL,
            production_date DATE NOT NULL COMMENT '(DC2Type:date_immutable)',
            reference VARCHAR(100) NOT NULL,
            product_name VARCHAR(150) DEFAULT 'Anchois' NOT NULL,
            responsible VARCHAR(150) DEFAULT NULL,
            notes LONGTEXT DEFAULT NULL,
            raw_quantity_kg NUMERIC(12, 3) DEFAULT '0.000' NOT NULL,
            finished_product_kg NUMERIC(12, 3) DEFAULT '0.000' NOT NULL,
            waste_kg NUMERIC(12, 3) DEFAULT '0.000' NOT NULL,
            loss_kg NUMERIC(12, 3) DEFAULT '0.000' NOT NULL,
            total_output_kg NUMERIC(12, 3) DEFAULT '0.000' NOT NULL,
            weight_gap_kg NUMERIC(12, 3) DEFAULT '0.000' NOT NULL,
            yield_percent NUMERIC(7, 2) DEFAULT '0.00' NOT NULL,
            hourly_workers INT DEFAULT 0 NOT NULL,
            hourly_hours NUMERIC(8, 2) DEFAULT '0.00' NOT NULL,
            hourly_rate NUMERIC(10, 2) DEFAULT '0.00' NOT NULL,
            hourly_labor_total NUMERIC(12, 2) DEFAULT '0.00' NOT NULL,
            cleaning_kg NUMERIC(12, 3) DEFAULT '0.000' NOT NULL,
            cleaning_price_per_kg NUMERIC(10, 2) DEFAULT '0.00' NOT NULL,
            boxing_kg NUMERIC(12, 3) DEFAULT '0.000' NOT NULL,
            boxing_price_per_kg NUMERIC(10, 2) DEFAULT '0.00' NOT NULL,
            other_task_amount NUMERIC(12, 2) DEFAULT '0.00' NOT NULL,
            task_labor_total NUMERIC(12, 2) DEFAULT '0.00' NOT NULL,
            fixed_salary_monthly_total NUMERIC(12, 2) DEFAULT '0.00' NOT NULL,
            fixed_salary_working_days INT DEFAULT 26 NOT NULL,
            fixed_salary_daily_total NUMERIC(12, 2) DEFAULT '0.00' NOT NULL,
            labor_total NUMERIC(12, 2) DEFAULT '0.00' NOT NULL,
            carton_count INT DEFAULT 0 NOT NULL,
            carton_unit_cost NUMERIC(10, 2) DEFAULT '0.00' NOT NULL,
            sachet_count INT DEFAULT 0 NOT NULL,
            sachet_unit_cost NUMERIC(10, 2) DEFAULT '0.00' NOT NULL,
            label_cost NUMERIC(10, 2) DEFAULT '0.00' NOT NULL,
            plastic_film_cost NUMERIC(10, 2) DEFAULT '0.00' NOT NULL,
            other_packaging_cost NUMERIC(10, 2) DEFAULT '0.00' NOT NULL,
            packaging_total NUMERIC(12, 2) DEFAULT '0.00' NOT NULL,
            configured_charges_total NUMERIC(12, 2) DEFAULT '0.00' NOT NULL,
            manual_charges_adjustment NUMERIC(12, 2) DEFAULT '0.00' NOT NULL,
            charges_total NUMERIC(12, 2) DEFAULT '0.00' NOT NULL,
            total_cost NUMERIC(12, 2) DEFAULT '0.00' NOT NULL,
            cost_per_input_kg NUMERIC(12, 2) DEFAULT '0.00' NOT NULL,
            cost_per_finished_kg NUMERIC(12, 2) DEFAULT '0.00' NOT NULL,
            created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
            updated_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
            UNIQUE INDEX uniq_daily_production_cost_reference (reference),
            INDEX idx_daily_production_cost_created_by (created_by_id),
            INDEX idx_daily_production_cost_updated_by (updated_by_id),
            INDEX idx_daily_production_cost_date (production_date),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");

        $this->addSql("CREATE TABLE daily_production_cost_charge_line (
            id INT AUTO_INCREMENT NOT NULL,
            daily_production_cost_id INT DEFAULT NULL,
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
            created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
            updated_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
            INDEX idx_daily_cost_charge_line_cost (daily_production_cost_id),
            INDEX idx_daily_cost_charge_line_config (charge_config_id),
            INDEX idx_daily_cost_charge_line_category (category),
            INDEX idx_daily_cost_charge_line_created_by (created_by_id),
            INDEX idx_daily_cost_charge_line_updated_by (updated_by_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");

        $this->addSql('ALTER TABLE daily_production_cost ADD CONSTRAINT FK_DAILY_COST_CREATED_BY FOREIGN KEY (created_by_id) REFERENCES app_user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE daily_production_cost ADD CONSTRAINT FK_DAILY_COST_UPDATED_BY FOREIGN KEY (updated_by_id) REFERENCES app_user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE daily_production_cost_charge_line ADD CONSTRAINT FK_DAILY_COST_LINE_COST FOREIGN KEY (daily_production_cost_id) REFERENCES daily_production_cost (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE daily_production_cost_charge_line ADD CONSTRAINT FK_DAILY_COST_LINE_CONFIG FOREIGN KEY (charge_config_id) REFERENCES cout_revient_charge_config (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE daily_production_cost_charge_line ADD CONSTRAINT FK_DAILY_COST_LINE_CREATED_BY FOREIGN KEY (created_by_id) REFERENCES app_user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE daily_production_cost_charge_line ADD CONSTRAINT FK_DAILY_COST_LINE_UPDATED_BY FOREIGN KEY (updated_by_id) REFERENCES app_user (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE daily_production_cost_charge_line DROP FOREIGN KEY FK_DAILY_COST_LINE_UPDATED_BY');
        $this->addSql('ALTER TABLE daily_production_cost_charge_line DROP FOREIGN KEY FK_DAILY_COST_LINE_CREATED_BY');
        $this->addSql('ALTER TABLE daily_production_cost_charge_line DROP FOREIGN KEY FK_DAILY_COST_LINE_CONFIG');
        $this->addSql('ALTER TABLE daily_production_cost_charge_line DROP FOREIGN KEY FK_DAILY_COST_LINE_COST');
        $this->addSql('ALTER TABLE daily_production_cost DROP FOREIGN KEY FK_DAILY_COST_UPDATED_BY');
        $this->addSql('ALTER TABLE daily_production_cost DROP FOREIGN KEY FK_DAILY_COST_CREATED_BY');
        $this->addSql('DROP TABLE daily_production_cost_charge_line');
        $this->addSql('DROP TABLE daily_production_cost');
    }
}
