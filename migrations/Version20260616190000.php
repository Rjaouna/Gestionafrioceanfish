<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260616190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds the statistics module to reuse dashboard charts.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            INSERT INTO app_module (name, slug, description, icon, route_name, is_active, created_at)
            SELECT 'Statistiques', 'statistics', 'Graphiques et indicateurs de pilotage.', 'bi-graph-up-arrow', 'app_statistics_index', 1, NOW()
            WHERE NOT EXISTS (SELECT 1 FROM app_module WHERE slug = 'statistics')
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM app_module WHERE slug = 'statistics'");
    }
}
