<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260702122000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds a student/other profile field to interim workers.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE interim_worker ADD worker_type VARCHAR(30) NOT NULL DEFAULT 'autre'");
        $this->addSql('CREATE INDEX idx_interim_worker_type ON interim_worker (worker_type)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_interim_worker_type ON interim_worker');
        $this->addSql('ALTER TABLE interim_worker DROP worker_type');
    }
}
