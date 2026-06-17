<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260617121000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Synchronizes document dates with their creation dates.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('UPDATE document SET document_date = DATE(created_at) WHERE document_date IS NULL AND created_at IS NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // Data normalization only; keep document dates on rollback.
    }
}
