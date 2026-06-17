<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260617143000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds maintenance sharing for intervenants, contracts and interventions.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE maintenance_share (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, created_by_id INT DEFAULT NULL, updated_by_id INT DEFAULT NULL, item_type VARCHAR(30) NOT NULL, item_id INT NOT NULL, can_view TINYINT(1) DEFAULT 1 NOT NULL, is_active TINYINT(1) DEFAULT 1 NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, UNIQUE INDEX uniq_maintenance_share_user (item_type, item_id, user_id), INDEX idx_maintenance_share_item (item_type, item_id), INDEX idx_maintenance_share_user (user_id), INDEX idx_maintenance_share_created_by (created_by_id), INDEX idx_maintenance_share_updated_by (updated_by_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE maintenance_share ADD CONSTRAINT FK_MAINTENANCE_SHARE_USER FOREIGN KEY (user_id) REFERENCES app_user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE maintenance_share ADD CONSTRAINT FK_MAINTENANCE_SHARE_CREATED_BY FOREIGN KEY (created_by_id) REFERENCES app_user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE maintenance_share ADD CONSTRAINT FK_MAINTENANCE_SHARE_UPDATED_BY FOREIGN KEY (updated_by_id) REFERENCES app_user (id) ON DELETE SET NULL');

        $this->addSql("INSERT IGNORE INTO maintenance_share (item_type, item_id, user_id, can_view, is_active, created_at, updated_at, created_by_id) SELECT 'intervenant', i.id, i.created_by_id, 1, 1, COALESCE(i.created_at, NOW()), NULL, i.created_by_id FROM intervenant i INNER JOIN app_user u ON u.id = i.created_by_id WHERE i.created_by_id IS NOT NULL AND CAST(u.roles AS CHAR) NOT LIKE '%ROLE_ADMIN%' AND CAST(u.roles AS CHAR) NOT LIKE '%ROLE_SUPER_ADMIN%'");
        $this->addSql("INSERT IGNORE INTO maintenance_share (item_type, item_id, user_id, can_view, is_active, created_at, updated_at, created_by_id) SELECT 'contract', c.id, c.created_by_id, 1, 1, COALESCE(c.created_at, NOW()), NULL, c.created_by_id FROM maintenance_contract c INNER JOIN app_user u ON u.id = c.created_by_id WHERE c.created_by_id IS NOT NULL AND CAST(u.roles AS CHAR) NOT LIKE '%ROLE_ADMIN%' AND CAST(u.roles AS CHAR) NOT LIKE '%ROLE_SUPER_ADMIN%'");
        $this->addSql("INSERT IGNORE INTO maintenance_share (item_type, item_id, user_id, can_view, is_active, created_at, updated_at, created_by_id) SELECT 'intervention', i.id, i.created_by_id, 1, 1, COALESCE(i.created_at, NOW()), NULL, i.created_by_id FROM intervention i INNER JOIN app_user u ON u.id = i.created_by_id WHERE i.created_by_id IS NOT NULL AND CAST(u.roles AS CHAR) NOT LIKE '%ROLE_ADMIN%' AND CAST(u.roles AS CHAR) NOT LIKE '%ROLE_SUPER_ADMIN%'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE maintenance_share DROP FOREIGN KEY FK_MAINTENANCE_SHARE_USER');
        $this->addSql('ALTER TABLE maintenance_share DROP FOREIGN KEY FK_MAINTENANCE_SHARE_CREATED_BY');
        $this->addSql('ALTER TABLE maintenance_share DROP FOREIGN KEY FK_MAINTENANCE_SHARE_UPDATED_BY');
        $this->addSql('DROP TABLE maintenance_share');
    }
}
