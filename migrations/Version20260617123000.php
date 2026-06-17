<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260617123000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Normalizes expense share audit column definitions.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE expense_share CHANGE created_at created_at DATETIME NOT NULL, CHANGE updated_at updated_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // Schema normalization only.
    }
}
