<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260618132500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds carton stock management tables to inventory.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE inventory_carton_stock (id INT AUTO_INCREMENT NOT NULL, created_by_id INT DEFAULT NULL, updated_by_id INT DEFAULT NULL, name VARCHAR(180) NOT NULL, source_sheet VARCHAR(180) DEFAULT NULL, description LONGTEXT DEFAULT NULL, is_active TINYINT(1) DEFAULT 1 NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_CARTON_STOCK_CREATED_BY (created_by_id), INDEX IDX_CARTON_STOCK_UPDATED_BY (updated_by_id), INDEX idx_inventory_carton_stock_active (is_active), UNIQUE INDEX uniq_inventory_carton_stock_name (name), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE inventory_carton_stock_line (id INT AUTO_INCREMENT NOT NULL, stock_id INT NOT NULL, created_by_id INT DEFAULT NULL, updated_by_id INT DEFAULT NULL, group_name VARCHAR(180) DEFAULT NULL, reference VARCHAR(180) NOT NULL, quantity INT DEFAULT NULL, unit_price NUMERIC(12, 3) DEFAULT NULL, total_amount NUMERIC(14, 3) DEFAULT NULL, line_type VARCHAR(30) NOT NULL, position INT NOT NULL, notes LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_CARTON_LINE_STOCK (stock_id), INDEX IDX_CARTON_LINE_CREATED_BY (created_by_id), INDEX IDX_CARTON_LINE_UPDATED_BY (updated_by_id), INDEX idx_inventory_carton_line_type (line_type), INDEX idx_inventory_carton_line_reference (reference), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE inventory_carton_stock ADD CONSTRAINT FK_CARTON_STOCK_CREATED_BY FOREIGN KEY (created_by_id) REFERENCES app_user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE inventory_carton_stock ADD CONSTRAINT FK_CARTON_STOCK_UPDATED_BY FOREIGN KEY (updated_by_id) REFERENCES app_user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE inventory_carton_stock_line ADD CONSTRAINT FK_CARTON_LINE_STOCK FOREIGN KEY (stock_id) REFERENCES inventory_carton_stock (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE inventory_carton_stock_line ADD CONSTRAINT FK_CARTON_LINE_CREATED_BY FOREIGN KEY (created_by_id) REFERENCES app_user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE inventory_carton_stock_line ADD CONSTRAINT FK_CARTON_LINE_UPDATED_BY FOREIGN KEY (updated_by_id) REFERENCES app_user (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE inventory_carton_stock_line DROP FOREIGN KEY FK_CARTON_LINE_STOCK');
        $this->addSql('ALTER TABLE inventory_carton_stock_line DROP FOREIGN KEY FK_CARTON_LINE_CREATED_BY');
        $this->addSql('ALTER TABLE inventory_carton_stock_line DROP FOREIGN KEY FK_CARTON_LINE_UPDATED_BY');
        $this->addSql('ALTER TABLE inventory_carton_stock DROP FOREIGN KEY FK_CARTON_STOCK_CREATED_BY');
        $this->addSql('ALTER TABLE inventory_carton_stock DROP FOREIGN KEY FK_CARTON_STOCK_UPDATED_BY');
        $this->addSql('DROP TABLE inventory_carton_stock_line');
        $this->addSql('DROP TABLE inventory_carton_stock');
    }
}
