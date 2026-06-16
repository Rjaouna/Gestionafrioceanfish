<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260617103000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds city and additional mobile numbers to contacts.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE contact ADD mobile_secondary VARCHAR(40) DEFAULT NULL, ADD mobile_tertiary VARCHAR(40) DEFAULT NULL, ADD city VARCHAR(120) DEFAULT NULL');
        $this->addSql('CREATE INDEX idx_contact_city ON contact (city)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_contact_city ON contact');
        $this->addSql('ALTER TABLE contact DROP mobile_secondary, DROP mobile_tertiary, DROP city');
    }
}
