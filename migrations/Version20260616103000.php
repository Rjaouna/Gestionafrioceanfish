<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260616103000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds the maintenance module with interventions, intervenants, contracts and history.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE intervenant (
              id INT AUTO_INCREMENT NOT NULL,
              firstname VARCHAR(100) NOT NULL,
              lastname VARCHAR(100) NOT NULL,
              email VARCHAR(180) DEFAULT NULL,
              phone VARCHAR(40) DEFAULT NULL,
              type VARCHAR(30) NOT NULL,
              speciality VARCHAR(160) DEFAULT NULL,
              is_active TINYINT DEFAULT 1 NOT NULL,
              notes LONGTEXT DEFAULT NULL,
              created_at DATETIME NOT NULL,
              updated_at DATETIME DEFAULT NULL,
              created_by_id INT DEFAULT NULL,
              updated_by_id INT DEFAULT NULL,
              INDEX idx_intervenant_type (type),
              INDEX idx_intervenant_active (is_active),
              INDEX idx_intervenant_created_by (created_by_id),
              INDEX idx_intervenant_updated_by (updated_by_id),
              PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE maintenance_contract (
              id INT AUTO_INCREMENT NOT NULL,
              reference VARCHAR(80) NOT NULL,
              customer_name VARCHAR(180) NOT NULL,
              customer_email VARCHAR(180) DEFAULT NULL,
              customer_phone VARCHAR(40) DEFAULT NULL,
              customer_address LONGTEXT DEFAULT NULL,
              contract_type VARCHAR(120) DEFAULT NULL,
              start_date DATE DEFAULT NULL,
              end_date DATE DEFAULT NULL,
              renewal_date DATE DEFAULT NULL,
              intervention_frequency VARCHAR(30) NOT NULL,
              amount NUMERIC(12, 2) DEFAULT NULL,
              status VARCHAR(30) NOT NULL,
              description LONGTEXT DEFAULT NULL,
              notes LONGTEXT DEFAULT NULL,
              is_active TINYINT DEFAULT 1 NOT NULL,
              created_at DATETIME NOT NULL,
              updated_at DATETIME DEFAULT NULL,
              created_by_id INT DEFAULT NULL,
              updated_by_id INT DEFAULT NULL,
              INDEX idx_maintenance_contract_status (status),
              INDEX idx_maintenance_contract_active (is_active),
              INDEX idx_maintenance_contract_created_by (created_by_id),
              INDEX idx_maintenance_contract_updated_by (updated_by_id),
              PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE intervention (
              id INT AUTO_INCREMENT NOT NULL,
              contract_id INT DEFAULT NULL,
              title VARCHAR(180) NOT NULL,
              reference VARCHAR(80) NOT NULL,
              customer_name VARCHAR(180) NOT NULL,
              customer_email VARCHAR(180) DEFAULT NULL,
              customer_phone VARCHAR(40) DEFAULT NULL,
              customer_address LONGTEXT DEFAULT NULL,
              planned_at DATETIME DEFAULT NULL,
              started_at DATETIME DEFAULT NULL,
              ended_at DATETIME DEFAULT NULL,
              priority VARCHAR(30) NOT NULL,
              status VARCHAR(30) NOT NULL,
              description LONGTEXT DEFAULT NULL,
              work_done LONGTEXT DEFAULT NULL,
              internal_notes LONGTEXT DEFAULT NULL,
              result_status VARCHAR(40) DEFAULT NULL,
              is_active TINYINT DEFAULT 1 NOT NULL,
              created_at DATETIME NOT NULL,
              updated_at DATETIME DEFAULT NULL,
              created_by_id INT DEFAULT NULL,
              updated_by_id INT DEFAULT NULL,
              INDEX idx_intervention_contract (contract_id),
              INDEX idx_intervention_status (status),
              INDEX idx_intervention_priority (priority),
              INDEX idx_intervention_active (is_active),
              INDEX idx_intervention_created_by (created_by_id),
              INDEX idx_intervention_updated_by (updated_by_id),
              PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE intervention_intervenant (
              id INT AUTO_INCREMENT NOT NULL,
              intervention_id INT NOT NULL,
              intervenant_id INT NOT NULL,
              role_on_intervention VARCHAR(120) DEFAULT NULL,
              assigned_at DATETIME NOT NULL,
              is_main_intervenant TINYINT DEFAULT 0 NOT NULL,
              created_at DATETIME NOT NULL,
              updated_at DATETIME DEFAULT NULL,
              created_by_id INT DEFAULT NULL,
              updated_by_id INT DEFAULT NULL,
              INDEX idx_intervention_intervenant_intervention (intervention_id),
              INDEX idx_intervention_intervenant_intervenant (intervenant_id),
              INDEX idx_intervention_intervenant_created_by (created_by_id),
              INDEX idx_intervention_intervenant_updated_by (updated_by_id),
              UNIQUE INDEX uniq_intervention_intervenant (intervention_id, intervenant_id),
              PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE intervention_history (
              id INT AUTO_INCREMENT NOT NULL,
              intervention_id INT NOT NULL,
              action VARCHAR(120) NOT NULL,
              old_status VARCHAR(30) DEFAULT NULL,
              new_status VARCHAR(30) DEFAULT NULL,
              comment LONGTEXT DEFAULT NULL,
              created_at DATETIME NOT NULL,
              updated_at DATETIME DEFAULT NULL,
              created_by_id INT DEFAULT NULL,
              updated_by_id INT DEFAULT NULL,
              INDEX idx_intervention_history_intervention (intervention_id),
              INDEX idx_intervention_history_created_by (created_by_id),
              INDEX idx_intervention_history_updated_by (updated_by_id),
              PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 ENGINE = InnoDB
        SQL);

        $this->addSql('ALTER TABLE intervenant ADD CONSTRAINT FK_INTERVENANT_CREATED_BY FOREIGN KEY (created_by_id) REFERENCES app_user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE intervenant ADD CONSTRAINT FK_INTERVENANT_UPDATED_BY FOREIGN KEY (updated_by_id) REFERENCES app_user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE maintenance_contract ADD CONSTRAINT FK_MAINTENANCE_CONTRACT_CREATED_BY FOREIGN KEY (created_by_id) REFERENCES app_user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE maintenance_contract ADD CONSTRAINT FK_MAINTENANCE_CONTRACT_UPDATED_BY FOREIGN KEY (updated_by_id) REFERENCES app_user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE intervention ADD CONSTRAINT FK_INTERVENTION_CONTRACT FOREIGN KEY (contract_id) REFERENCES maintenance_contract (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE intervention ADD CONSTRAINT FK_INTERVENTION_CREATED_BY FOREIGN KEY (created_by_id) REFERENCES app_user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE intervention ADD CONSTRAINT FK_INTERVENTION_UPDATED_BY FOREIGN KEY (updated_by_id) REFERENCES app_user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE intervention_intervenant ADD CONSTRAINT FK_INTERVENTION_INTERVENANT_INTERVENTION FOREIGN KEY (intervention_id) REFERENCES intervention (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE intervention_intervenant ADD CONSTRAINT FK_INTERVENTION_INTERVENANT_INTERVENANT FOREIGN KEY (intervenant_id) REFERENCES intervenant (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE intervention_intervenant ADD CONSTRAINT FK_INTERVENTION_INTERVENANT_CREATED_BY FOREIGN KEY (created_by_id) REFERENCES app_user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE intervention_intervenant ADD CONSTRAINT FK_INTERVENTION_INTERVENANT_UPDATED_BY FOREIGN KEY (updated_by_id) REFERENCES app_user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE intervention_history ADD CONSTRAINT FK_INTERVENTION_HISTORY_INTERVENTION FOREIGN KEY (intervention_id) REFERENCES intervention (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE intervention_history ADD CONSTRAINT FK_INTERVENTION_HISTORY_CREATED_BY FOREIGN KEY (created_by_id) REFERENCES app_user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE intervention_history ADD CONSTRAINT FK_INTERVENTION_HISTORY_UPDATED_BY FOREIGN KEY (updated_by_id) REFERENCES app_user (id) ON DELETE SET NULL');
        $this->addSql(<<<'SQL'
            INSERT INTO app_module (name, slug, description, icon, route_name, is_active, created_at)
            SELECT 'Maintenance', 'maintenance', 'Interventions, intervenants et contrats de maintenance.', 'bi-tools', 'app_maintenance_intervention_index', 1, NOW()
            WHERE NOT EXISTS (SELECT 1 FROM app_module WHERE slug = 'maintenance')
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM app_module WHERE slug = 'maintenance'");
        $this->addSql('ALTER TABLE intervention_history DROP FOREIGN KEY FK_INTERVENTION_HISTORY_INTERVENTION');
        $this->addSql('ALTER TABLE intervention_history DROP FOREIGN KEY FK_INTERVENTION_HISTORY_CREATED_BY');
        $this->addSql('ALTER TABLE intervention_history DROP FOREIGN KEY FK_INTERVENTION_HISTORY_UPDATED_BY');
        $this->addSql('ALTER TABLE intervention_intervenant DROP FOREIGN KEY FK_INTERVENTION_INTERVENANT_INTERVENTION');
        $this->addSql('ALTER TABLE intervention_intervenant DROP FOREIGN KEY FK_INTERVENTION_INTERVENANT_INTERVENANT');
        $this->addSql('ALTER TABLE intervention_intervenant DROP FOREIGN KEY FK_INTERVENTION_INTERVENANT_CREATED_BY');
        $this->addSql('ALTER TABLE intervention_intervenant DROP FOREIGN KEY FK_INTERVENTION_INTERVENANT_UPDATED_BY');
        $this->addSql('ALTER TABLE intervention DROP FOREIGN KEY FK_INTERVENTION_CONTRACT');
        $this->addSql('ALTER TABLE intervention DROP FOREIGN KEY FK_INTERVENTION_CREATED_BY');
        $this->addSql('ALTER TABLE intervention DROP FOREIGN KEY FK_INTERVENTION_UPDATED_BY');
        $this->addSql('ALTER TABLE maintenance_contract DROP FOREIGN KEY FK_MAINTENANCE_CONTRACT_CREATED_BY');
        $this->addSql('ALTER TABLE maintenance_contract DROP FOREIGN KEY FK_MAINTENANCE_CONTRACT_UPDATED_BY');
        $this->addSql('ALTER TABLE intervenant DROP FOREIGN KEY FK_INTERVENANT_CREATED_BY');
        $this->addSql('ALTER TABLE intervenant DROP FOREIGN KEY FK_INTERVENANT_UPDATED_BY');
        $this->addSql('DROP TABLE intervention_history');
        $this->addSql('DROP TABLE intervention_intervenant');
        $this->addSql('DROP TABLE intervention');
        $this->addSql('DROP TABLE maintenance_contract');
        $this->addSql('DROP TABLE intervenant');
    }
}
