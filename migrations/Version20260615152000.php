<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260615152000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds validation and activation states to password entries.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE password_entry ADD is_validated TINYINT DEFAULT 1 NOT NULL, ADD is_active TINYINT DEFAULT 1 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE password_entry DROP is_validated, DROP is_active');
    }
}
