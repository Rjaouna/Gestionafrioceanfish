<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260706143000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add tunnel entry date and return-to-positive-room tracking to fish receptions';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE fish_reception ADD date_entree_tunnel DATE DEFAULT NULL, ADD chambre_remise_en_chambre VARCHAR(120) DEFAULT NULL, ADD date_remise_en_chambre DATE DEFAULT NULL, ADD heure_remise_en_chambre TIME DEFAULT NULL, ADD temperature_chambre_remise NUMERIC(6, 2) DEFAULT NULL, ADD temperature_produit_remise NUMERIC(6, 2) DEFAULT NULL, ADD quantite_remise_en_chambre NUMERIC(12, 3) DEFAULT '0.000' NOT NULL, ADD remise_en_chambre_at DATETIME DEFAULT NULL, ADD remise_en_chambre_by_id INT DEFAULT NULL");
        $this->addSql('CREATE INDEX idx_fish_reception_return_storage_by ON fish_reception (remise_en_chambre_by_id)');
        $this->addSql('ALTER TABLE fish_reception ADD CONSTRAINT FK_FISH_RECEPTION_RETURN_STORAGE_BY FOREIGN KEY (remise_en_chambre_by_id) REFERENCES app_user (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE fish_reception DROP FOREIGN KEY FK_FISH_RECEPTION_RETURN_STORAGE_BY');
        $this->addSql('DROP INDEX idx_fish_reception_return_storage_by ON fish_reception');
        $this->addSql('ALTER TABLE fish_reception DROP date_entree_tunnel, DROP chambre_remise_en_chambre, DROP date_remise_en_chambre, DROP heure_remise_en_chambre, DROP temperature_chambre_remise, DROP temperature_produit_remise, DROP quantite_remise_en_chambre, DROP remise_en_chambre_at, DROP remise_en_chambre_by_id');
    }
}
