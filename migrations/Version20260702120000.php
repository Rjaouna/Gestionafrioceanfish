<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260702120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds interim worker management with private photos, documents, module access and trash support.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE interim_worker (
              id INT AUTO_INCREMENT NOT NULL,
              created_by_id INT DEFAULT NULL,
              updated_by_id INT DEFAULT NULL,
              deleted_by_id INT DEFAULT NULL,
              last_name VARCHAR(120) NOT NULL,
              first_name VARCHAR(120) NOT NULL,
              address LONGTEXT DEFAULT NULL,
              position VARCHAR(160) NOT NULL,
              registration_number VARCHAR(80) NOT NULL,
              phone VARCHAR(40) NOT NULL,
              birth_date DATE DEFAULT NULL,
              birth_place VARCHAR(120) DEFAULT NULL,
              cin VARCHAR(40) DEFAULT NULL,
              family_situation VARCHAR(40) NOT NULL,
              children_count INT NOT NULL DEFAULT 0,
              hire_date DATE NOT NULL,
              mission_end_date DATE DEFAULT NULL,
              temp_agency VARCHAR(180) DEFAULT NULL,
              manager_observations LONGTEXT DEFAULT NULL,
              internal_comment LONGTEXT DEFAULT NULL,
              status VARCHAR(40) NOT NULL,
              is_active TINYINT(1) NOT NULL DEFAULT 1,
              photo_file_name VARCHAR(255) DEFAULT NULL,
              photo_original_file_name VARCHAR(255) DEFAULT NULL,
              photo_mime_type VARCHAR(160) DEFAULT NULL,
              photo_file_size INT DEFAULT NULL,
              is_deleted TINYINT(1) NOT NULL DEFAULT 0,
              deleted_at DATETIME DEFAULT NULL,
              delete_reason LONGTEXT DEFAULT NULL,
              created_at DATETIME NOT NULL,
              updated_at DATETIME DEFAULT NULL,
              UNIQUE INDEX uniq_interim_worker_registration_number (registration_number),
              INDEX idx_interim_worker_position (position),
              INDEX idx_interim_worker_status (status),
              INDEX idx_interim_worker_family_situation (family_situation),
              INDEX idx_interim_worker_hire_date (hire_date),
              INDEX idx_interim_worker_created_by (created_by_id),
              INDEX idx_interim_worker_updated_by (updated_by_id),
              INDEX IDX_INTERIM_WORKER_DELETED_BY (deleted_by_id),
              PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE interim_worker_document (
              id INT AUTO_INCREMENT NOT NULL,
              worker_id INT NOT NULL,
              created_by_id INT DEFAULT NULL,
              updated_by_id INT DEFAULT NULL,
              file_name VARCHAR(255) NOT NULL,
              original_file_name VARCHAR(255) NOT NULL,
              mime_type VARCHAR(160) NOT NULL,
              file_size INT NOT NULL,
              created_at DATETIME NOT NULL,
              updated_at DATETIME DEFAULT NULL,
              INDEX idx_interim_worker_document_worker (worker_id),
              INDEX idx_interim_worker_document_created_by (created_by_id),
              INDEX idx_interim_worker_document_updated_by (updated_by_id),
              PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);

        $this->addSql('ALTER TABLE interim_worker ADD CONSTRAINT FK_INTERIM_WORKER_CREATED_BY FOREIGN KEY (created_by_id) REFERENCES app_user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE interim_worker ADD CONSTRAINT FK_INTERIM_WORKER_UPDATED_BY FOREIGN KEY (updated_by_id) REFERENCES app_user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE interim_worker ADD CONSTRAINT FK_INTERIM_WORKER_DELETED_BY FOREIGN KEY (deleted_by_id) REFERENCES app_user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE interim_worker_document ADD CONSTRAINT FK_INTERIM_WORKER_DOCUMENT_WORKER FOREIGN KEY (worker_id) REFERENCES interim_worker (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE interim_worker_document ADD CONSTRAINT FK_INTERIM_WORKER_DOCUMENT_CREATED_BY FOREIGN KEY (created_by_id) REFERENCES app_user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE interim_worker_document ADD CONSTRAINT FK_INTERIM_WORKER_DOCUMENT_UPDATED_BY FOREIGN KEY (updated_by_id) REFERENCES app_user (id) ON DELETE SET NULL');

        $this->addSql(<<<'SQL'
            INSERT INTO app_module (name, slug, description, icon, route_name, is_active, created_at)
            SELECT 'Intérimaires', 'interimaires', 'Fiches de poste, suivi RH et documents des intérimaires.', 'bi-person-vcard', 'app_interim_worker_index', 1, NOW()
            WHERE NOT EXISTS (SELECT 1 FROM app_module WHERE slug = 'interimaires')
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM app_module WHERE slug = 'interimaires'");
        $this->addSql('ALTER TABLE interim_worker_document DROP FOREIGN KEY FK_INTERIM_WORKER_DOCUMENT_WORKER');
        $this->addSql('ALTER TABLE interim_worker_document DROP FOREIGN KEY FK_INTERIM_WORKER_DOCUMENT_CREATED_BY');
        $this->addSql('ALTER TABLE interim_worker_document DROP FOREIGN KEY FK_INTERIM_WORKER_DOCUMENT_UPDATED_BY');
        $this->addSql('ALTER TABLE interim_worker DROP FOREIGN KEY FK_INTERIM_WORKER_CREATED_BY');
        $this->addSql('ALTER TABLE interim_worker DROP FOREIGN KEY FK_INTERIM_WORKER_UPDATED_BY');
        $this->addSql('ALTER TABLE interim_worker DROP FOREIGN KEY FK_INTERIM_WORKER_DELETED_BY');
        $this->addSql('DROP TABLE interim_worker_document');
        $this->addSql('DROP TABLE interim_worker');
    }
}
