<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260709153000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds generated client contracts and the Contracts application module.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE generated_contract (
                id INT AUTO_INCREMENT NOT NULL,
                created_by_id INT DEFAULT NULL,
                updated_by_id INT DEFAULT NULL,
                deleted_by_id INT DEFAULT NULL,
                last_generated_by_id INT DEFAULT NULL,
                reference VARCHAR(80) NOT NULL,
                contract_type VARCHAR(50) NOT NULL,
                contract_date DATE NOT NULL,
                campaign VARCHAR(30) NOT NULL,
                client_company_name VARCHAR(180) NOT NULL,
                client_address LONGTEXT NOT NULL,
                representative_title VARCHAR(30) NOT NULL,
                representative_name VARCHAR(180) NOT NULL,
                representative_id_number VARCHAR(80) NOT NULL,
                signing_city VARCHAR(120) NOT NULL,
                status VARCHAR(30) NOT NULL,
                last_generated_at DATETIME DEFAULT NULL,
                internal_notes LONGTEXT DEFAULT NULL,
                is_deleted TINYINT(1) DEFAULT 0 NOT NULL,
                deleted_at DATETIME DEFAULT NULL,
                delete_reason LONGTEXT DEFAULT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME DEFAULT NULL,
                UNIQUE INDEX uniq_generated_contract_reference (reference),
                INDEX idx_generated_contract_date (contract_date),
                INDEX idx_generated_contract_type (contract_type),
                INDEX idx_generated_contract_status (status),
                INDEX idx_generated_contract_client (client_company_name),
                INDEX IDX_A92E1FE0B03A8386 (created_by_id),
                INDEX IDX_A92E1FE0896DBBDE (updated_by_id),
                INDEX IDX_A92E1FE0C76F1F52 (deleted_by_id),
                INDEX IDX_A92E1FE0EB06982B (last_generated_by_id),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql('ALTER TABLE generated_contract ADD CONSTRAINT FK_GENERATED_CONTRACT_CREATED_BY FOREIGN KEY (created_by_id) REFERENCES app_user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE generated_contract ADD CONSTRAINT FK_GENERATED_CONTRACT_UPDATED_BY FOREIGN KEY (updated_by_id) REFERENCES app_user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE generated_contract ADD CONSTRAINT FK_GENERATED_CONTRACT_DELETED_BY FOREIGN KEY (deleted_by_id) REFERENCES app_user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE generated_contract ADD CONSTRAINT FK_GENERATED_CONTRACT_LAST_GENERATED_BY FOREIGN KEY (last_generated_by_id) REFERENCES app_user (id) ON DELETE SET NULL');
        $this->addSql(<<<'SQL'
            INSERT INTO app_module (name, slug, description, icon, route_name, is_active, created_at)
            SELECT 'Contrats', 'contracts', 'Creation, suivi et generation PDF des contrats clients.', 'bi-file-earmark-text', 'app_generated_contract_index', 1, NOW()
            WHERE NOT EXISTS (SELECT 1 FROM app_module WHERE slug = 'contracts')
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM app_module WHERE slug = 'contracts'");
        $this->addSql('ALTER TABLE generated_contract DROP FOREIGN KEY FK_GENERATED_CONTRACT_CREATED_BY');
        $this->addSql('ALTER TABLE generated_contract DROP FOREIGN KEY FK_GENERATED_CONTRACT_UPDATED_BY');
        $this->addSql('ALTER TABLE generated_contract DROP FOREIGN KEY FK_GENERATED_CONTRACT_DELETED_BY');
        $this->addSql('ALTER TABLE generated_contract DROP FOREIGN KEY FK_GENERATED_CONTRACT_LAST_GENERATED_BY');
        $this->addSql('DROP TABLE generated_contract');
    }
}
