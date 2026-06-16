<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260617110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds the contact person name field to contacts.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE contact ADD contact_person_name VARCHAR(180) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE contact DROP contact_person_name');
    }
}
