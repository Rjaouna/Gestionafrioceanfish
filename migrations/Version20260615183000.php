<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260615183000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds the contact directory module with user sharing.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE contact (
              id INT AUTO_INCREMENT NOT NULL,
              full_name VARCHAR(180) NOT NULL,
              type VARCHAR(120) NOT NULL,
              email VARCHAR(180) DEFAULT NULL,
              mobile VARCHAR(40) DEFAULT NULL,
              landline VARCHAR(40) DEFAULT NULL,
              postal_address LONGTEXT DEFAULT NULL,
              is_active TINYINT DEFAULT 1 NOT NULL,
              created_at DATETIME NOT NULL,
              updated_at DATETIME DEFAULT NULL,
              created_by_id INT DEFAULT NULL,
              updated_by_id INT DEFAULT NULL,
              INDEX idx_contact_created_by (created_by_id),
              INDEX idx_contact_updated_by (updated_by_id),
              INDEX idx_contact_type (type),
              PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE contact_share (
              id INT AUTO_INCREMENT NOT NULL,
              can_view TINYINT DEFAULT 1 NOT NULL,
              is_active TINYINT DEFAULT 1 NOT NULL,
              contact_id INT NOT NULL,
              user_id INT NOT NULL,
              created_at DATETIME NOT NULL,
              updated_at DATETIME DEFAULT NULL,
              created_by_id INT DEFAULT NULL,
              updated_by_id INT DEFAULT NULL,
              INDEX idx_contact_share_contact (contact_id),
              INDEX idx_contact_share_user (user_id),
              INDEX idx_contact_share_created_by (created_by_id),
              INDEX idx_contact_share_updated_by (updated_by_id),
              UNIQUE INDEX uniq_contact_share_user (contact_id, user_id),
              PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 ENGINE = InnoDB
        SQL);
        $this->addSql('ALTER TABLE contact ADD CONSTRAINT FK_CONTACT_CREATED_BY FOREIGN KEY (created_by_id) REFERENCES app_user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE contact ADD CONSTRAINT FK_CONTACT_UPDATED_BY FOREIGN KEY (updated_by_id) REFERENCES app_user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE contact_share ADD CONSTRAINT FK_CONTACT_SHARE_CONTACT FOREIGN KEY (contact_id) REFERENCES contact (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE contact_share ADD CONSTRAINT FK_CONTACT_SHARE_USER FOREIGN KEY (user_id) REFERENCES app_user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE contact_share ADD CONSTRAINT FK_CONTACT_SHARE_CREATED_BY FOREIGN KEY (created_by_id) REFERENCES app_user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE contact_share ADD CONSTRAINT FK_CONTACT_SHARE_UPDATED_BY FOREIGN KEY (updated_by_id) REFERENCES app_user (id) ON DELETE SET NULL');
        $this->addSql(<<<'SQL'
            INSERT INTO app_module (name, slug, description, icon, route_name, is_active, created_at)
            SELECT 'Carnet de contacts', 'contacts', 'Fournisseurs, clients, dépanneurs et contacts partagés.', 'bi-person-lines-fill', 'app_contact_index', 1, NOW()
            WHERE NOT EXISTS (SELECT 1 FROM app_module WHERE slug = 'contacts')
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM app_module WHERE slug = 'contacts'");
        $this->addSql('ALTER TABLE contact_share DROP FOREIGN KEY FK_CONTACT_SHARE_CONTACT');
        $this->addSql('ALTER TABLE contact_share DROP FOREIGN KEY FK_CONTACT_SHARE_USER');
        $this->addSql('ALTER TABLE contact_share DROP FOREIGN KEY FK_CONTACT_SHARE_CREATED_BY');
        $this->addSql('ALTER TABLE contact_share DROP FOREIGN KEY FK_CONTACT_SHARE_UPDATED_BY');
        $this->addSql('ALTER TABLE contact DROP FOREIGN KEY FK_CONTACT_CREATED_BY');
        $this->addSql('ALTER TABLE contact DROP FOREIGN KEY FK_CONTACT_UPDATED_BY');
        $this->addSql('DROP TABLE contact_share');
        $this->addSql('DROP TABLE contact');
    }
}
