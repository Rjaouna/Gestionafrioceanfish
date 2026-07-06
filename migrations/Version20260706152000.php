<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260706152000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Merge packaging and return-to-room stock quantities for fish receptions';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("UPDATE fish_reception SET quantite_remise_en_chambre = quantite_conditionnee, chambre_remise_en_chambre = COALESCE(NULLIF(chambre_remise_en_chambre, ''), chambre_froide), date_remise_en_chambre = COALESCE(date_remise_en_chambre, date_conditionnement), heure_remise_en_chambre = COALESCE(heure_remise_en_chambre, heure_fin_conditionnement), remise_en_chambre_at = COALESCE(remise_en_chambre_at, updated_at), statut = CASE WHEN statut = 'emballee' THEN 'remise_chambre' ELSE statut END WHERE quantite_conditionnee > quantite_remise_en_chambre");
    }

    public function down(Schema $schema): void
    {
        // Data migration intentionally not reversible.
    }
}
