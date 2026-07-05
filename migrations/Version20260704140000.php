<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260704140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds fish reception workflow and links receptions to production cost lots.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE fish_reception (
                id INT AUTO_INCREMENT NOT NULL,
                created_by_id INT DEFAULT NULL,
                updated_by_id INT DEFAULT NULL,
                deleted_by_id INT DEFAULT NULL,
                received_by_id INT DEFAULT NULL,
                treatment_started_by_id INT DEFAULT NULL,
                stored_by_id INT DEFAULT NULL,
                closed_by_id INT DEFAULT NULL,
                blocked_by_id INT DEFAULT NULL,
                numero_reception VARCHAR(100) NOT NULL,
                numero_lot VARCHAR(100) NOT NULL,
                date_reception DATE NOT NULL,
                heure_debut_reception TIME DEFAULT NULL,
                heure_fin_reception TIME DEFAULT NULL,
                fournisseur VARCHAR(150) NOT NULL,
                provenance VARCHAR(150) DEFAULT NULL,
                matricule_vehicule VARCHAR(80) DEFAULT NULL,
                chauffeur VARCHAR(150) DEFAULT NULL,
                espece_poisson VARCHAR(120) NOT NULL,
                nom_scientifique VARCHAR(150) DEFAULT NULL,
                presentation_produit VARCHAR(120) NOT NULL,
                etat_produit VARCHAR(120) NOT NULL,
                numero_bon_livraison VARCHAR(120) DEFAULT NULL,
                quantite_indiquee_bl NUMERIC(12, 3) DEFAULT '0.000' NOT NULL,
                quantite_receptionnee NUMERIC(12, 3) DEFAULT '0.000' NOT NULL,
                nombre_caisses_reception INT DEFAULT 0 NOT NULL,
                temperature_poisson_reception NUMERIC(6, 2) DEFAULT NULL,
                categorie_fraicheur VARCHAR(80) NOT NULL,
                presence_glace TINYINT(1) DEFAULT 1 NOT NULL,
                heure_debut_traitement TIME DEFAULT NULL,
                temperature_eau_glacee NUMERIC(6, 2) DEFAULT NULL,
                nombre_caisses_apres_traitement INT DEFAULT 0 NOT NULL,
                poids_moyen_par_caisse NUMERIC(8, 3) DEFAULT '0.000' NOT NULL,
                nombre_moules INT DEFAULT 0 NOT NULL,
                nombre_caisses_par_palette INT DEFAULT 0 NOT NULL,
                nombre_total_palettes INT DEFAULT 0 NOT NULL,
                quantite_totale_preparee NUMERIC(12, 3) DEFAULT '0.000' NOT NULL,
                tunnel VARCHAR(80) DEFAULT NULL,
                heure_entree_tunnel TIME DEFAULT NULL,
                temperature_tunnel NUMERIC(6, 2) DEFAULT NULL,
                date_sortie_tunnel DATE DEFAULT NULL,
                temperature_coeur_produit NUMERIC(6, 2) DEFAULT NULL,
                quantite_congelee NUMERIC(12, 3) DEFAULT '0.000' NOT NULL,
                chambre_froide VARCHAR(120) DEFAULT NULL,
                temperature_chambre NUMERIC(6, 2) DEFAULT NULL,
                date_entree_stockage DATE DEFAULT NULL,
                heure_entree_stockage TIME DEFAULT NULL,
                quantite_stockee NUMERIC(12, 3) DEFAULT '0.000' NOT NULL,
                date_conditionnement DATE DEFAULT NULL,
                heure_debut_conditionnement TIME DEFAULT NULL,
                heure_fin_conditionnement TIME DEFAULT NULL,
                produit_conditionne VARCHAR(150) DEFAULT NULL,
                quantite_conditionnee NUMERIC(12, 3) DEFAULT '0.000' NOT NULL,
                poids_net NUMERIC(12, 3) DEFAULT '0.000' NOT NULL,
                temperature_stockage NUMERIC(6, 2) DEFAULT NULL,
                quantite_totale_expediee NUMERIC(12, 3) DEFAULT '0.000' NOT NULL,
                destination_finale_client VARCHAR(150) DEFAULT NULL,
                observations LONGTEXT DEFAULT NULL,
                responsable_production VARCHAR(150) DEFAULT NULL,
                signature_responsable VARCHAR(150) DEFAULT NULL,
                statut VARCHAR(30) DEFAULT 'brouillon' NOT NULL,
                quantite_utilisee_production NUMERIC(12, 3) DEFAULT '0.000' NOT NULL,
                received_at DATETIME DEFAULT NULL,
                treatment_started_at DATETIME DEFAULT NULL,
                stored_at DATETIME DEFAULT NULL,
                closed_at DATETIME DEFAULT NULL,
                blocked_at DATETIME DEFAULT NULL,
                block_reason LONGTEXT DEFAULT NULL,
                is_deleted TINYINT(1) DEFAULT 0 NOT NULL,
                deleted_at DATETIME DEFAULT NULL,
                delete_reason LONGTEXT DEFAULT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME DEFAULT NULL,
                UNIQUE INDEX uniq_fish_reception_numero_reception (numero_reception),
                UNIQUE INDEX uniq_fish_reception_numero_lot (numero_lot),
                INDEX idx_fish_reception_date (date_reception),
                INDEX idx_fish_reception_statut (statut),
                INDEX idx_fish_reception_fournisseur (fournisseur),
                INDEX idx_fish_reception_espece (espece_poisson),
                INDEX idx_fish_reception_chambre (chambre_froide),
                INDEX idx_fish_reception_created_by (created_by_id),
                INDEX idx_fish_reception_updated_by (updated_by_id),
                INDEX idx_fish_reception_deleted_by (deleted_by_id),
                INDEX idx_fish_reception_received_by (received_by_id),
                INDEX idx_fish_reception_treatment_by (treatment_started_by_id),
                INDEX idx_fish_reception_stored_by (stored_by_id),
                INDEX idx_fish_reception_closed_by (closed_by_id),
                INDEX idx_fish_reception_blocked_by (blocked_by_id),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);

        $this->addSql('ALTER TABLE fish_reception ADD CONSTRAINT FK_FISH_RECEPTION_CREATED_BY FOREIGN KEY (created_by_id) REFERENCES app_user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE fish_reception ADD CONSTRAINT FK_FISH_RECEPTION_UPDATED_BY FOREIGN KEY (updated_by_id) REFERENCES app_user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE fish_reception ADD CONSTRAINT FK_FISH_RECEPTION_DELETED_BY FOREIGN KEY (deleted_by_id) REFERENCES app_user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE fish_reception ADD CONSTRAINT FK_FISH_RECEPTION_RECEIVED_BY FOREIGN KEY (received_by_id) REFERENCES app_user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE fish_reception ADD CONSTRAINT FK_FISH_RECEPTION_TREATMENT_BY FOREIGN KEY (treatment_started_by_id) REFERENCES app_user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE fish_reception ADD CONSTRAINT FK_FISH_RECEPTION_STORED_BY FOREIGN KEY (stored_by_id) REFERENCES app_user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE fish_reception ADD CONSTRAINT FK_FISH_RECEPTION_CLOSED_BY FOREIGN KEY (closed_by_id) REFERENCES app_user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE fish_reception ADD CONSTRAINT FK_FISH_RECEPTION_BLOCKED_BY FOREIGN KEY (blocked_by_id) REFERENCES app_user (id) ON DELETE SET NULL');

        $this->addSql('ALTER TABLE cout_revient ADD reception_id INT DEFAULT NULL');
        $this->addSql('CREATE INDEX idx_cout_revient_reception ON cout_revient (reception_id)');
        $this->addSql('ALTER TABLE cout_revient ADD CONSTRAINT FK_COUT_REVIENT_RECEPTION FOREIGN KEY (reception_id) REFERENCES fish_reception (id) ON DELETE SET NULL');

        $this->addSql(<<<'SQL'
            INSERT INTO app_module (name, slug, description, icon, route_name, is_active, created_at, updated_at)
            VALUES ('Receptions', 'receptions', 'Reception poisson, workflow traitement, congelation, stockage et disponibilite production.', 'bi-clipboard2-check', 'app_fish_reception_index', 1, NOW(), NULL)
            ON DUPLICATE KEY UPDATE
                name = VALUES(name),
                description = VALUES(description),
                icon = VALUES(icon),
                route_name = VALUES(route_name),
                is_active = 1,
                updated_at = NOW()
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE uma FROM user_module_access uma INNER JOIN app_module m ON uma.module_id = m.id WHERE m.slug = 'receptions'");
        $this->addSql("DELETE FROM app_module WHERE slug = 'receptions'");
        $this->addSql('ALTER TABLE cout_revient DROP FOREIGN KEY FK_COUT_REVIENT_RECEPTION');
        $this->addSql('DROP INDEX idx_cout_revient_reception ON cout_revient');
        $this->addSql('ALTER TABLE cout_revient DROP reception_id');
        $this->addSql('DROP TABLE fish_reception');
    }
}
