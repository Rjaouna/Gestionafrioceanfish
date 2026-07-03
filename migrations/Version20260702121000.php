<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260702121000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Renames the interim worker deleted-by index to Doctrine expected name.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE interim_worker RENAME INDEX idx_interim_worker_deleted_by TO IDX_B3950F1EC76F1F52');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE interim_worker RENAME INDEX IDX_B3950F1EC76F1F52 TO idx_interim_worker_deleted_by');
    }
}
