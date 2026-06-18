<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260618121000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds missing audit timestamp columns to inventory movements.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE inventory_movement ADD created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, ADD updated_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE inventory_movement ALTER created_at DROP DEFAULT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE inventory_movement DROP created_at, DROP updated_at');
    }
}
