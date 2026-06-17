<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260617150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds company name to maintenance intervenants.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE intervenant ADD company_name VARCHAR(180) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE intervenant DROP company_name');
    }
}
