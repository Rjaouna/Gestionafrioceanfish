<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260705162000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds packaging waste, loss and hourly cost tracking to fish receptions.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE fish_reception ADD poids_dechets_emballage NUMERIC(12, 3) DEFAULT '0.000' NOT NULL, ADD poids_pertes_emballage NUMERIC(12, 3) DEFAULT '0.000' NOT NULL, ADD cout_horaire_emballage NUMERIC(10, 2) DEFAULT '0.00' NOT NULL, ADD cout_emballage NUMERIC(12, 2) DEFAULT '0.00' NOT NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE fish_reception DROP poids_dechets_emballage, DROP poids_pertes_emballage, DROP cout_horaire_emballage, DROP cout_emballage');
    }
}
