<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260705152000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds reception entry costs used by cost calculation.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE fish_reception ADD operation_type VARCHAR(40) DEFAULT 'achat_matiere' NOT NULL, ADD reception_prix_achat_kg NUMERIC(10, 2) DEFAULT '0.00' NOT NULL, ADD reception_montant_achat_total NUMERIC(12, 2) DEFAULT '0.00' NOT NULL, ADD reception_frais_transport NUMERIC(10, 2) DEFAULT '0.00' NOT NULL, ADD reception_frais_dechargement NUMERIC(10, 2) DEFAULT '0.00' NOT NULL, ADD reception_frais_glace_consommables NUMERIC(10, 2) DEFAULT '0.00' NOT NULL, ADD reception_frais_controle_qualite NUMERIC(10, 2) DEFAULT '0.00' NOT NULL, ADD reception_autres_frais NUMERIC(10, 2) DEFAULT '0.00' NOT NULL, ADD reception_reference_facture VARCHAR(120) DEFAULT NULL, ADD reception_devise VARCHAR(10) DEFAULT 'MAD' NOT NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE fish_reception DROP operation_type, DROP reception_prix_achat_kg, DROP reception_montant_achat_total, DROP reception_frais_transport, DROP reception_frais_dechargement, DROP reception_frais_glace_consommables, DROP reception_frais_controle_qualite, DROP reception_autres_frais, DROP reception_reference_facture, DROP reception_devise');
    }
}
