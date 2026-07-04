<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260704120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds production cost calculation module.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE cout_revient (
                id INT AUTO_INCREMENT NOT NULL,
                created_by_id INT DEFAULT NULL,
                updated_by_id INT DEFAULT NULL,
                deleted_by_id INT DEFAULT NULL,
                validated_by_id INT DEFAULT NULL,
                date_production DATE NOT NULL,
                numero_lot VARCHAR(100) NOT NULL,
                produit VARCHAR(150) NOT NULL,
                espece_poisson VARCHAR(150) DEFAULT NULL,
                client VARCHAR(150) DEFAULT NULL,
                responsable_production VARCHAR(150) DEFAULT NULL,
                statut VARCHAR(30) DEFAULT 'brouillon' NOT NULL,
                observation LONGTEXT DEFAULT NULL,
                poids_brut_recu NUMERIC(10, 3) DEFAULT '0.000' NOT NULL,
                poids_mis_en_production NUMERIC(10, 3) DEFAULT '0.000' NOT NULL,
                prix_achat_kg NUMERIC(10, 2) DEFAULT '0.00' NOT NULL,
                frais_transport_achat NUMERIC(10, 2) DEFAULT '0.00' NOT NULL,
                autres_frais_achat NUMERIC(10, 2) DEFAULT '0.00' NOT NULL,
                cout_matiere_premiere NUMERIC(12, 2) DEFAULT '0.00' NOT NULL,
                poids_produit_fini NUMERIC(10, 3) DEFAULT '0.000' NOT NULL,
                poids_dechets NUMERIC(10, 3) DEFAULT '0.000' NOT NULL,
                poids_perte NUMERIC(10, 3) DEFAULT '0.000' NOT NULL,
                rendement_pourcentage NUMERIC(6, 2) DEFAULT '0.00' NOT NULL,
                mode_calcul_main_oeuvre VARCHAR(30) DEFAULT 'montant_direct' NOT NULL,
                nombre_operatrices INT DEFAULT 0 NOT NULL,
                nombre_heures NUMERIC(8, 2) DEFAULT '0.00' NOT NULL,
                cout_horaire_moyen NUMERIC(10, 2) DEFAULT '0.00' NOT NULL,
                prix_tache_kg NUMERIC(10, 2) DEFAULT '0.00' NOT NULL,
                kg_traites_main_oeuvre NUMERIC(10, 3) DEFAULT '0.000' NOT NULL,
                cout_main_oeuvre_direct NUMERIC(10, 2) DEFAULT '0.00' NOT NULL,
                cout_main_oeuvre NUMERIC(12, 2) DEFAULT '0.00' NOT NULL,
                nombre_cartons INT DEFAULT 0 NOT NULL,
                prix_carton NUMERIC(10, 2) DEFAULT '0.00' NOT NULL,
                nombre_sachets INT DEFAULT 0 NOT NULL,
                prix_sachet NUMERIC(10, 2) DEFAULT '0.00' NOT NULL,
                cout_etiquettes NUMERIC(10, 2) DEFAULT '0.00' NOT NULL,
                cout_film_plastique NUMERIC(10, 2) DEFAULT '0.00' NOT NULL,
                autres_cout_emballage NUMERIC(10, 2) DEFAULT '0.00' NOT NULL,
                cout_emballage_total NUMERIC(12, 2) DEFAULT '0.00' NOT NULL,
                cout_electricite NUMERIC(10, 2) DEFAULT '0.00' NOT NULL,
                cout_eau NUMERIC(10, 2) DEFAULT '0.00' NOT NULL,
                cout_glace NUMERIC(10, 2) DEFAULT '0.00' NOT NULL,
                cout_nettoyage NUMERIC(10, 2) DEFAULT '0.00' NOT NULL,
                cout_maintenance NUMERIC(10, 2) DEFAULT '0.00' NOT NULL,
                cout_transport_livraison NUMERIC(10, 2) DEFAULT '0.00' NOT NULL,
                autres_charges NUMERIC(10, 2) DEFAULT '0.00' NOT NULL,
                cout_charges_total NUMERIC(12, 2) DEFAULT '0.00' NOT NULL,
                cout_total_production NUMERIC(12, 2) DEFAULT '0.00' NOT NULL,
                cout_revient_kg NUMERIC(12, 2) DEFAULT '0.00' NOT NULL,
                prix_vente_kg NUMERIC(10, 2) DEFAULT NULL,
                marge_kg NUMERIC(12, 2) DEFAULT '0.00' NOT NULL,
                marge_totale NUMERIC(12, 2) DEFAULT '0.00' NOT NULL,
                taux_marge_pourcentage NUMERIC(6, 2) DEFAULT '0.00' NOT NULL,
                validated_at DATETIME DEFAULT NULL,
                is_deleted TINYINT(1) DEFAULT 0 NOT NULL,
                deleted_at DATETIME DEFAULT NULL,
                delete_reason LONGTEXT DEFAULT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME DEFAULT NULL,
                UNIQUE INDEX uniq_cout_revient_numero_lot (numero_lot),
                INDEX idx_cout_revient_date_production (date_production),
                INDEX idx_cout_revient_produit (produit),
                INDEX idx_cout_revient_client (client),
                INDEX idx_cout_revient_statut (statut),
                INDEX idx_cout_revient_created_by (created_by_id),
                INDEX idx_cout_revient_updated_by (updated_by_id),
                INDEX idx_cout_revient_deleted_by (deleted_by_id),
                INDEX idx_cout_revient_validated_by (validated_by_id),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);

        $this->addSql('ALTER TABLE cout_revient ADD CONSTRAINT FK_COUT_REVIENT_CREATED_BY FOREIGN KEY (created_by_id) REFERENCES app_user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE cout_revient ADD CONSTRAINT FK_COUT_REVIENT_UPDATED_BY FOREIGN KEY (updated_by_id) REFERENCES app_user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE cout_revient ADD CONSTRAINT FK_COUT_REVIENT_DELETED_BY FOREIGN KEY (deleted_by_id) REFERENCES app_user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE cout_revient ADD CONSTRAINT FK_COUT_REVIENT_VALIDATED_BY FOREIGN KEY (validated_by_id) REFERENCES app_user (id) ON DELETE SET NULL');

        $this->addSql(<<<'SQL'
            INSERT INTO app_module (name, slug, description, icon, route_name, is_active, created_at, updated_at)
            VALUES ('Cout de revient', 'cout-revient', 'Calcul des couts de production, rendements et marges par lot.', 'bi-calculator', 'app_cout_revient_index', 1, NOW(), NULL)
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
        $this->addSql("DELETE uma FROM user_module_access uma INNER JOIN app_module m ON uma.module_id = m.id WHERE m.slug = 'cout-revient'");
        $this->addSql("DELETE FROM app_module WHERE slug = 'cout-revient'");
        $this->addSql('DROP TABLE cout_revient');
    }
}
