<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260707113000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds treatment waste and loss weights to fish reception freezing workflow.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE fish_reception ADD poids_dechets_traitement NUMERIC(12, 3) DEFAULT 0.000 NOT NULL, ADD poids_pertes_traitement NUMERIC(12, 3) DEFAULT 0.000 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE fish_reception DROP poids_dechets_traitement, DROP poids_pertes_traitement');
    }
}
