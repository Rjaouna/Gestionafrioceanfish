<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260708143000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Allows interim worker phone to stay empty for quick worker creation.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE interim_worker CHANGE phone phone VARCHAR(20) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql("UPDATE interim_worker SET phone = '' WHERE phone IS NULL");
        $this->addSql('ALTER TABLE interim_worker CHANGE phone phone VARCHAR(20) NOT NULL');
    }
}
