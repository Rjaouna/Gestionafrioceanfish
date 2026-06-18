<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260618140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds the three-state logistics tracking classification to inventory items.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE inventory_item ADD logistics_status VARCHAR(30) DEFAULT 'legacy_remaining' NOT NULL");
        $this->addSql('CREATE INDEX idx_inventory_item_logistics_status ON inventory_item (logistics_status)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_inventory_item_logistics_status ON inventory_item');
        $this->addSql('ALTER TABLE inventory_item DROP logistics_status');
    }
}
