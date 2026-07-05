<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260705113000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds truck and loading details to fish reception shipments.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE fish_reception ADD expedition_date_depart DATE DEFAULT NULL, ADD expedition_heure_depart TIME DEFAULT NULL, ADD expedition_matricule_vehicule VARCHAR(80) DEFAULT NULL, ADD expedition_chauffeur VARCHAR(150) DEFAULT NULL, ADD expedition_responsable_chargement VARCHAR(150) DEFAULT NULL, ADD expedition_temperature_produit NUMERIC(6, 2) DEFAULT NULL, ADD expedition_numero_plomb VARCHAR(80) DEFAULT NULL, ADD expedition_observations LONGTEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE fish_reception DROP expedition_date_depart, DROP expedition_heure_depart, DROP expedition_matricule_vehicule, DROP expedition_chauffeur, DROP expedition_responsable_chargement, DROP expedition_temperature_produit, DROP expedition_numero_plomb, DROP expedition_observations');
    }
}
