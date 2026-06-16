<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260615171000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds the document management module, private documents and document shares.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE document (
              id INT AUTO_INCREMENT NOT NULL,
              name VARCHAR(180) NOT NULL,
              file_name VARCHAR(255) NOT NULL,
              original_file_name VARCHAR(255) NOT NULL,
              description LONGTEXT DEFAULT NULL,
              mime_type VARCHAR(160) NOT NULL,
              file_size INT NOT NULL,
              is_active TINYINT DEFAULT 1 NOT NULL,
              created_at DATETIME NOT NULL,
              updated_at DATETIME DEFAULT NULL,
              created_by_id INT DEFAULT NULL,
              updated_by_id INT DEFAULT NULL,
              INDEX idx_document_created_by (created_by_id),
              INDEX idx_document_updated_by (updated_by_id),
              PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE document_share (
              id INT AUTO_INCREMENT NOT NULL,
              can_view TINYINT DEFAULT 1 NOT NULL,
              can_download TINYINT DEFAULT 1 NOT NULL,
              email_sent_at DATETIME DEFAULT NULL,
              expires_at DATETIME DEFAULT NULL,
              is_active TINYINT DEFAULT 1 NOT NULL,
              document_id INT NOT NULL,
              user_id INT NOT NULL,
              created_at DATETIME NOT NULL,
              updated_at DATETIME DEFAULT NULL,
              created_by_id INT DEFAULT NULL,
              updated_by_id INT DEFAULT NULL,
              INDEX idx_document_share_document (document_id),
              INDEX idx_document_share_user (user_id),
              INDEX idx_document_share_created_by (created_by_id),
              INDEX idx_document_share_updated_by (updated_by_id),
              UNIQUE INDEX uniq_document_share_user (document_id, user_id),
              PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 ENGINE = InnoDB
        SQL);
        $this->addSql('ALTER TABLE document ADD CONSTRAINT FK_DOCUMENT_CREATED_BY FOREIGN KEY (created_by_id) REFERENCES app_user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE document ADD CONSTRAINT FK_DOCUMENT_UPDATED_BY FOREIGN KEY (updated_by_id) REFERENCES app_user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE document_share ADD CONSTRAINT FK_DOCUMENT_SHARE_DOCUMENT FOREIGN KEY (document_id) REFERENCES document (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE document_share ADD CONSTRAINT FK_DOCUMENT_SHARE_USER FOREIGN KEY (user_id) REFERENCES app_user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE document_share ADD CONSTRAINT FK_DOCUMENT_SHARE_CREATED_BY FOREIGN KEY (created_by_id) REFERENCES app_user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE document_share ADD CONSTRAINT FK_DOCUMENT_SHARE_UPDATED_BY FOREIGN KEY (updated_by_id) REFERENCES app_user (id) ON DELETE SET NULL');
        $this->addSql(<<<'SQL'
            INSERT INTO app_module (name, slug, description, icon, route_name, is_active, created_at)
            SELECT 'Gestion des documents', 'documents', 'Documents privés, partage sécurisé et téléchargement contrôlé.', 'bi-folder2-open', 'app_document_index', 1, NOW()
            WHERE NOT EXISTS (SELECT 1 FROM app_module WHERE slug = 'documents')
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM app_module WHERE slug = 'documents'");
        $this->addSql('ALTER TABLE document_share DROP FOREIGN KEY FK_DOCUMENT_SHARE_DOCUMENT');
        $this->addSql('ALTER TABLE document_share DROP FOREIGN KEY FK_DOCUMENT_SHARE_USER');
        $this->addSql('ALTER TABLE document_share DROP FOREIGN KEY FK_DOCUMENT_SHARE_CREATED_BY');
        $this->addSql('ALTER TABLE document_share DROP FOREIGN KEY FK_DOCUMENT_SHARE_UPDATED_BY');
        $this->addSql('ALTER TABLE document DROP FOREIGN KEY FK_DOCUMENT_CREATED_BY');
        $this->addSql('ALTER TABLE document DROP FOREIGN KEY FK_DOCUMENT_UPDATED_BY');
        $this->addSql('DROP TABLE document_share');
        $this->addSql('DROP TABLE document');
    }
}
