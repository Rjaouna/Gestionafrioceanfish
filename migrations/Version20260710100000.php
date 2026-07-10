<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260710100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds internal staff flag to interim workers.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE interim_worker ADD is_internal_staff TINYINT(1) DEFAULT 0 NOT NULL');
        $this->addSql('CREATE INDEX idx_interim_worker_internal_staff ON interim_worker (is_internal_staff)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_interim_worker_internal_staff ON interim_worker');
        $this->addSql('ALTER TABLE interim_worker DROP is_internal_staff');
    }
}
