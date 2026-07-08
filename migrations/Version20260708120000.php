<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260708120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds fish yield study module.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE fish_yield_study (
              id INT AUTO_INCREMENT NOT NULL,
              created_by_id INT DEFAULT NULL,
              updated_by_id INT DEFAULT NULL,
              deleted_by_id INT DEFAULT NULL,
              reference VARCHAR(80) NOT NULL,
              study_date DATE NOT NULL,
              client_name VARCHAR(180) DEFAULT NULL,
              species_name VARCHAR(180) NOT NULL,
              has_mixed_fish TINYINT(1) NOT NULL DEFAULT 0,
              mixed_fish_name VARCHAR(180) DEFAULT NULL,
              raw_box_weight NUMERIC(12, 3) NOT NULL DEFAULT '0.000',
              thawed_box_weight NUMERIC(12, 3) NOT NULL DEFAULT '0.000',
              pieces_per_kg NUMERIC(10, 2) NOT NULL DEFAULT '0.00',
              finished_product_weight NUMERIC(12, 3) NOT NULL DEFAULT '0.000',
              waste_weight NUMERIC(12, 3) NOT NULL DEFAULT '0.000',
              loss_weight NUMERIC(12, 3) NOT NULL DEFAULT '0.000',
              container_weight NUMERIC(12, 3) NOT NULL DEFAULT '0.000',
              operator_name VARCHAR(150) DEFAULT NULL,
              observations LONGTEXT DEFAULT NULL,
              is_deleted TINYINT(1) NOT NULL DEFAULT 0,
              deleted_at DATETIME DEFAULT NULL,
              delete_reason LONGTEXT DEFAULT NULL,
              created_at DATETIME NOT NULL,
              updated_at DATETIME DEFAULT NULL,
              UNIQUE INDEX uniq_fish_yield_study_reference (reference),
              INDEX idx_fish_yield_study_date (study_date),
              INDEX idx_fish_yield_study_client (client_name),
              INDEX idx_fish_yield_study_species (species_name),
              INDEX idx_fish_yield_study_created_by (created_by_id),
              INDEX idx_fish_yield_study_updated_by (updated_by_id),
              INDEX idx_fish_yield_study_deleted_by (deleted_by_id),
              PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);

        $this->addSql('ALTER TABLE fish_yield_study ADD CONSTRAINT FK_FISH_YIELD_STUDY_CREATED_BY FOREIGN KEY (created_by_id) REFERENCES app_user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE fish_yield_study ADD CONSTRAINT FK_FISH_YIELD_STUDY_UPDATED_BY FOREIGN KEY (updated_by_id) REFERENCES app_user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE fish_yield_study ADD CONSTRAINT FK_FISH_YIELD_STUDY_DELETED_BY FOREIGN KEY (deleted_by_id) REFERENCES app_user (id) ON DELETE SET NULL');

        $this->addSql(<<<'SQL'
            INSERT INTO app_module (name, slug, description, icon, route_name, is_active, created_at, updated_at)
            VALUES ('Etudes rendement poisson', 'etudes-rendement-poisson', 'Essais client sur caisse echantillon, taux eau, rendement filet et estimation conteneur.', 'bi-clipboard2-pulse', 'app_fish_yield_study_index', 1, NOW(), NULL)
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
        $this->addSql("DELETE uma FROM user_module_access uma INNER JOIN app_module m ON uma.module_id = m.id WHERE m.slug = 'etudes-rendement-poisson'");
        $this->addSql("DELETE FROM app_module WHERE slug = 'etudes-rendement-poisson'");
        $this->addSql('ALTER TABLE fish_yield_study DROP FOREIGN KEY FK_FISH_YIELD_STUDY_CREATED_BY');
        $this->addSql('ALTER TABLE fish_yield_study DROP FOREIGN KEY FK_FISH_YIELD_STUDY_UPDATED_BY');
        $this->addSql('ALTER TABLE fish_yield_study DROP FOREIGN KEY FK_FISH_YIELD_STUDY_DELETED_BY');
        $this->addSql('DROP TABLE fish_yield_study');
    }
}
