<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260618133500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Aligns carton stock audit columns and index names with Doctrine mapping.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE inventory_carton_stock CHANGE created_at created_at DATETIME NOT NULL, CHANGE updated_at updated_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE inventory_carton_stock RENAME INDEX idx_carton_stock_created_by TO idx_inventory_carton_stock_created_by');
        $this->addSql('ALTER TABLE inventory_carton_stock RENAME INDEX idx_carton_stock_updated_by TO idx_inventory_carton_stock_updated_by');
        $this->addSql('ALTER TABLE inventory_carton_stock_line CHANGE created_at created_at DATETIME NOT NULL, CHANGE updated_at updated_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE inventory_carton_stock_line RENAME INDEX idx_carton_line_stock TO idx_inventory_carton_line_stock');
        $this->addSql('ALTER TABLE inventory_carton_stock_line RENAME INDEX idx_carton_line_created_by TO idx_inventory_carton_line_created_by');
        $this->addSql('ALTER TABLE inventory_carton_stock_line RENAME INDEX idx_carton_line_updated_by TO idx_inventory_carton_line_updated_by');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE inventory_carton_stock RENAME INDEX idx_inventory_carton_stock_created_by TO idx_carton_stock_created_by');
        $this->addSql('ALTER TABLE inventory_carton_stock RENAME INDEX idx_inventory_carton_stock_updated_by TO idx_carton_stock_updated_by');
        $this->addSql('ALTER TABLE inventory_carton_stock_line RENAME INDEX idx_inventory_carton_line_stock TO idx_carton_line_stock');
        $this->addSql('ALTER TABLE inventory_carton_stock_line RENAME INDEX idx_inventory_carton_line_created_by TO idx_carton_line_created_by');
        $this->addSql('ALTER TABLE inventory_carton_stock_line RENAME INDEX idx_inventory_carton_line_updated_by TO idx_carton_line_updated_by');
    }
}
