<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260705163000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds tunnel exit time to fish reception freezing traceability.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE fish_reception ADD heure_sortie_tunnel TIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE fish_reception DROP heure_sortie_tunnel');
    }
}
