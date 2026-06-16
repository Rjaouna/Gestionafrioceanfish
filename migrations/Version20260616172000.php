<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260616172000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Repairs missing audit columns on expenses when the expenses migration was already marked as executed.';
    }

    public function up(Schema $schema): void
    {
        if (!$schema->hasTable('expense')) {
            return;
        }

        $table = $schema->getTable('expense');

        if (!$table->hasColumn('created_at')) {
            $this->addSql('ALTER TABLE expense ADD created_at DATETIME DEFAULT NULL');
            $this->addSql('UPDATE expense SET created_at = NOW() WHERE created_at IS NULL');
            $this->addSql('ALTER TABLE expense MODIFY created_at DATETIME NOT NULL');
        }

        if (!$table->hasColumn('updated_at')) {
            $this->addSql('ALTER TABLE expense ADD updated_at DATETIME DEFAULT NULL');
        }
    }

    public function down(Schema $schema): void
    {
        if (!$schema->hasTable('expense')) {
            return;
        }

        $table = $schema->getTable('expense');

        if ($table->hasColumn('updated_at')) {
            $this->addSql('ALTER TABLE expense DROP updated_at');
        }

        if ($table->hasColumn('created_at')) {
            $this->addSql('ALTER TABLE expense DROP created_at');
        }
    }
}
