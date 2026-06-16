<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260617113000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds the contact person position field to contacts.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE contact ADD contact_person_position VARCHAR(180) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE contact DROP contact_person_position');
    }
}
