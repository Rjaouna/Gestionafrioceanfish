<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260702125000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds supplier phone tracking to consumable stock items.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE consumable_stock_item ADD supplier_phone VARCHAR(60) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE consumable_stock_item DROP supplier_phone');
    }
}
