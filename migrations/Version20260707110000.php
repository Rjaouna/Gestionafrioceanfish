<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260707110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds personnel attendance tracking and default attendance rates.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE interim_attendance_rate (
              id INT AUTO_INCREMENT NOT NULL,
              created_by_id INT DEFAULT NULL,
              updated_by_id INT DEFAULT NULL,
              code VARCHAR(80) NOT NULL,
              label VARCHAR(120) NOT NULL,
              mode VARCHAR(20) NOT NULL,
              unit_label VARCHAR(40) NOT NULL,
              amount NUMERIC(10, 2) NOT NULL,
              active TINYINT(1) NOT NULL DEFAULT 1,
              created_at DATETIME NOT NULL,
              updated_at DATETIME DEFAULT NULL,
              UNIQUE INDEX uniq_interim_attendance_rate_code (code),
              INDEX idx_interim_attendance_rate_mode (mode),
              INDEX idx_interim_attendance_rate_created_by (created_by_id),
              INDEX idx_interim_attendance_rate_updated_by (updated_by_id),
              PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE interim_attendance (
              id INT AUTO_INCREMENT NOT NULL,
              worker_id INT NOT NULL,
              created_by_id INT DEFAULT NULL,
              updated_by_id INT DEFAULT NULL,
              attendance_date DATE NOT NULL,
              mode VARCHAR(20) NOT NULL,
              morning_present TINYINT(1) NOT NULL DEFAULT 1,
              morning_start TIME DEFAULT NULL,
              morning_end TIME DEFAULT NULL,
              afternoon_present TINYINT(1) NOT NULL DEFAULT 1,
              afternoon_start TIME DEFAULT NULL,
              afternoon_end TIME DEFAULT NULL,
              hourly_rate NUMERIC(10, 2) NOT NULL,
              task_type VARCHAR(80) DEFAULT NULL,
              task_unit VARCHAR(40) DEFAULT NULL,
              task_quantity NUMERIC(10, 3) DEFAULT NULL,
              task_unit_price NUMERIC(10, 2) DEFAULT NULL,
              total_hours NUMERIC(8, 2) NOT NULL,
              total_amount NUMERIC(12, 2) NOT NULL,
              comment LONGTEXT DEFAULT NULL,
              created_at DATETIME NOT NULL,
              updated_at DATETIME DEFAULT NULL,
              INDEX idx_interim_attendance_worker (worker_id),
              INDEX idx_interim_attendance_date (attendance_date),
              INDEX idx_interim_attendance_mode (mode),
              INDEX idx_interim_attendance_created_by (created_by_id),
              INDEX idx_interim_attendance_updated_by (updated_by_id),
              PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);

        $this->addSql('ALTER TABLE interim_attendance_rate ADD CONSTRAINT FK_INTERIM_ATTENDANCE_RATE_CREATED_BY FOREIGN KEY (created_by_id) REFERENCES app_user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE interim_attendance_rate ADD CONSTRAINT FK_INTERIM_ATTENDANCE_RATE_UPDATED_BY FOREIGN KEY (updated_by_id) REFERENCES app_user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE interim_attendance ADD CONSTRAINT FK_INTERIM_ATTENDANCE_WORKER FOREIGN KEY (worker_id) REFERENCES interim_worker (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE interim_attendance ADD CONSTRAINT FK_INTERIM_ATTENDANCE_CREATED_BY FOREIGN KEY (created_by_id) REFERENCES app_user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE interim_attendance ADD CONSTRAINT FK_INTERIM_ATTENDANCE_UPDATED_BY FOREIGN KEY (updated_by_id) REFERENCES app_user (id) ON DELETE SET NULL');

        $this->addSql(<<<'SQL'
            INSERT INTO interim_attendance_rate (code, label, mode, unit_label, amount, active, created_at)
            VALUES
              ('hourly_default', 'Taux horaire par defaut', 'hourly', 'heure', 0, 1, NOW()),
              ('task_cleaning', 'Tache nettoyage', 'task', 'nettoyage', 0, 1, NOW()),
              ('task_boxing', 'Tache mise en caisse', 'task', 'caisse', 0, 1, NOW())
        SQL);

        $this->addSql(<<<'SQL'
            INSERT INTO app_module (name, slug, description, icon, route_name, is_active, created_at, updated_at)
            SELECT 'Pointage personnel', 'pointage-personnel', 'Pointages horaires, taches et tarifs du personnel.', 'bi-calendar-check', 'app_interim_attendance_index', 1, NOW(), NOW()
            WHERE NOT EXISTS (SELECT 1 FROM app_module WHERE slug = 'pointage-personnel')
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE uma FROM user_module_access uma INNER JOIN app_module m ON uma.module_id = m.id WHERE m.slug = 'pointage-personnel'");
        $this->addSql("DELETE FROM app_module WHERE slug = 'pointage-personnel'");
        $this->addSql('ALTER TABLE interim_attendance DROP FOREIGN KEY FK_INTERIM_ATTENDANCE_WORKER');
        $this->addSql('ALTER TABLE interim_attendance DROP FOREIGN KEY FK_INTERIM_ATTENDANCE_CREATED_BY');
        $this->addSql('ALTER TABLE interim_attendance DROP FOREIGN KEY FK_INTERIM_ATTENDANCE_UPDATED_BY');
        $this->addSql('ALTER TABLE interim_attendance_rate DROP FOREIGN KEY FK_INTERIM_ATTENDANCE_RATE_CREATED_BY');
        $this->addSql('ALTER TABLE interim_attendance_rate DROP FOREIGN KEY FK_INTERIM_ATTENDANCE_RATE_UPDATED_BY');
        $this->addSql('DROP TABLE interim_attendance');
        $this->addSql('DROP TABLE interim_attendance_rate');
    }
}
