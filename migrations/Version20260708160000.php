<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260708160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds waste sales tracking module.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE waste_sale (
                id INT AUTO_INCREMENT NOT NULL,
                created_by_id INT DEFAULT NULL,
                updated_by_id INT DEFAULT NULL,
                deleted_by_id INT DEFAULT NULL,
                reference VARCHAR(80) NOT NULL,
                sale_date DATE NOT NULL,
                buyer_name VARCHAR(180) NOT NULL,
                payment_method VARCHAR(40) NOT NULL,
                weight_kg NUMERIC(12, 3) DEFAULT 0 NOT NULL,
                unit_price NUMERIC(8, 2) DEFAULT 0.60 NOT NULL,
                total_amount NUMERIC(12, 2) DEFAULT 0 NOT NULL,
                notes LONGTEXT DEFAULT NULL,
                is_deleted TINYINT(1) DEFAULT 0 NOT NULL,
                deleted_at DATETIME DEFAULT NULL,
                delete_reason LONGTEXT DEFAULT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME DEFAULT NULL,
                UNIQUE INDEX uniq_waste_sale_reference (reference),
                INDEX idx_waste_sale_date (sale_date),
                INDEX idx_waste_sale_buyer (buyer_name),
                INDEX idx_waste_sale_payment (payment_method),
                INDEX idx_waste_sale_created_by (created_by_id),
                INDEX idx_waste_sale_updated_by (updated_by_id),
                INDEX idx_waste_sale_deleted_by (deleted_by_id),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql('ALTER TABLE waste_sale ADD CONSTRAINT FK_WASTE_SALE_CREATED_BY FOREIGN KEY (created_by_id) REFERENCES app_user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE waste_sale ADD CONSTRAINT FK_WASTE_SALE_UPDATED_BY FOREIGN KEY (updated_by_id) REFERENCES app_user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE waste_sale ADD CONSTRAINT FK_WASTE_SALE_DELETED_BY FOREIGN KEY (deleted_by_id) REFERENCES app_user (id) ON DELETE SET NULL');
        $this->addSql(<<<'SQL'
            INSERT INTO app_module (name, slug, description, icon, route_name, is_active, created_at)
            SELECT 'Ventes dechets', 'ventes-dechets', 'Suivi des ventes de dechets poisson, acheteurs, paiements et statistiques.', 'bi-recycle', 'app_waste_sale_index', 1, NOW()
            WHERE NOT EXISTS (SELECT 1 FROM app_module WHERE slug = 'ventes-dechets')
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM app_module WHERE slug = 'ventes-dechets'");
        $this->addSql('ALTER TABLE waste_sale DROP FOREIGN KEY FK_WASTE_SALE_CREATED_BY');
        $this->addSql('ALTER TABLE waste_sale DROP FOREIGN KEY FK_WASTE_SALE_UPDATED_BY');
        $this->addSql('ALTER TABLE waste_sale DROP FOREIGN KEY FK_WASTE_SALE_DELETED_BY');
        $this->addSql('DROP TABLE waste_sale');
    }
}
