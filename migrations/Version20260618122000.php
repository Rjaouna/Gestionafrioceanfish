<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260618122000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds dimensions and color fields to inventory items.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE inventory_item ADD dimensions VARCHAR(120) DEFAULT NULL, ADD color VARCHAR(80) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE inventory_item DROP dimensions, DROP color');
    }
}
