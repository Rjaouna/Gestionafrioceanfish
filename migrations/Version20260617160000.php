<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260617160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds the Agenda RDV module with appointments, participants and history.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE appointment (
              id INT AUTO_INCREMENT NOT NULL,
              title VARCHAR(180) NOT NULL,
              reference VARCHAR(80) NOT NULL,
              description LONGTEXT DEFAULT NULL,
              location VARCHAR(180) DEFAULT NULL,
              meeting_link VARCHAR(255) DEFAULT NULL,
              start_at DATETIME NOT NULL,
              end_at DATETIME NOT NULL,
              all_day TINYINT DEFAULT 0 NOT NULL,
              status VARCHAR(30) NOT NULL,
              priority VARCHAR(30) NOT NULL,
              appointment_type VARCHAR(40) NOT NULL,
              customer_name VARCHAR(180) DEFAULT NULL,
              customer_email VARCHAR(180) DEFAULT NULL,
              customer_phone VARCHAR(40) DEFAULT NULL,
              reminder_at DATETIME DEFAULT NULL,
              color VARCHAR(30) DEFAULT NULL,
              cancellation_reason LONGTEXT DEFAULT NULL,
              is_active TINYINT DEFAULT 1 NOT NULL,
              is_deleted TINYINT DEFAULT 0 NOT NULL,
              deleted_at DATETIME DEFAULT NULL,
              delete_reason LONGTEXT DEFAULT NULL,
              created_at DATETIME NOT NULL,
              updated_at DATETIME DEFAULT NULL,
              created_by_id INT DEFAULT NULL,
              updated_by_id INT DEFAULT NULL,
              deleted_by_id INT DEFAULT NULL,
              UNIQUE INDEX uniq_appointment_reference (reference),
              INDEX idx_appointment_start (start_at),
              INDEX idx_appointment_end (end_at),
              INDEX idx_appointment_status (status),
              INDEX idx_appointment_priority (priority),
              INDEX idx_appointment_type (appointment_type),
              INDEX idx_appointment_active (is_active),
              INDEX idx_appointment_deleted (is_deleted),
              INDEX idx_appointment_created_by (created_by_id),
              INDEX idx_appointment_updated_by (updated_by_id),
              INDEX idx_appointment_deleted_by (deleted_by_id),
              PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE appointment_participant (
              id INT AUTO_INCREMENT NOT NULL,
              appointment_id INT NOT NULL,
              user_id INT NOT NULL,
              role_in_appointment VARCHAR(30) NOT NULL,
              response_status VARCHAR(30) NOT NULL,
              notified_at DATETIME DEFAULT NULL,
              reminder_sent_at DATETIME DEFAULT NULL,
              is_required TINYINT DEFAULT 1 NOT NULL,
              is_active TINYINT DEFAULT 1 NOT NULL,
              created_at DATETIME NOT NULL,
              updated_at DATETIME DEFAULT NULL,
              created_by_id INT DEFAULT NULL,
              updated_by_id INT DEFAULT NULL,
              UNIQUE INDEX uniq_appointment_participant_user (appointment_id, user_id),
              INDEX idx_appointment_participant_appointment (appointment_id),
              INDEX idx_appointment_participant_user (user_id),
              INDEX idx_appointment_participant_active (is_active),
              INDEX idx_appointment_participant_response (response_status),
              INDEX idx_appointment_participant_created_by (created_by_id),
              INDEX idx_appointment_participant_updated_by (updated_by_id),
              PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE appointment_history (
              id INT AUTO_INCREMENT NOT NULL,
              appointment_id INT NOT NULL,
              action VARCHAR(120) NOT NULL,
              old_value LONGTEXT DEFAULT NULL,
              new_value LONGTEXT DEFAULT NULL,
              comment LONGTEXT DEFAULT NULL,
              created_at DATETIME NOT NULL,
              updated_at DATETIME DEFAULT NULL,
              created_by_id INT DEFAULT NULL,
              updated_by_id INT DEFAULT NULL,
              INDEX idx_appointment_history_appointment (appointment_id),
              INDEX idx_appointment_history_created_at (created_at),
              INDEX idx_appointment_history_created_by (created_by_id),
              INDEX idx_appointment_history_updated_by (updated_by_id),
              PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 ENGINE = InnoDB
        SQL);
        $this->addSql('ALTER TABLE appointment ADD CONSTRAINT FK_APPOINTMENT_CREATED_BY FOREIGN KEY (created_by_id) REFERENCES app_user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE appointment ADD CONSTRAINT FK_APPOINTMENT_UPDATED_BY FOREIGN KEY (updated_by_id) REFERENCES app_user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE appointment ADD CONSTRAINT FK_APPOINTMENT_DELETED_BY FOREIGN KEY (deleted_by_id) REFERENCES app_user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE appointment_participant ADD CONSTRAINT FK_APPOINTMENT_PARTICIPANT_APPOINTMENT FOREIGN KEY (appointment_id) REFERENCES appointment (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE appointment_participant ADD CONSTRAINT FK_APPOINTMENT_PARTICIPANT_USER FOREIGN KEY (user_id) REFERENCES app_user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE appointment_participant ADD CONSTRAINT FK_APPOINTMENT_PARTICIPANT_CREATED_BY FOREIGN KEY (created_by_id) REFERENCES app_user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE appointment_participant ADD CONSTRAINT FK_APPOINTMENT_PARTICIPANT_UPDATED_BY FOREIGN KEY (updated_by_id) REFERENCES app_user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE appointment_history ADD CONSTRAINT FK_APPOINTMENT_HISTORY_APPOINTMENT FOREIGN KEY (appointment_id) REFERENCES appointment (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE appointment_history ADD CONSTRAINT FK_APPOINTMENT_HISTORY_CREATED_BY FOREIGN KEY (created_by_id) REFERENCES app_user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE appointment_history ADD CONSTRAINT FK_APPOINTMENT_HISTORY_UPDATED_BY FOREIGN KEY (updated_by_id) REFERENCES app_user (id) ON DELETE SET NULL');
        $this->addSql(<<<'SQL'
            INSERT INTO app_module (name, slug, description, icon, route_name, is_active, created_at)
            SELECT 'Agenda - RDV', 'agenda', 'Calendrier professionnel, rendez-vous, participants et rappels.', 'bi-calendar-check', 'app_appointment_calendar', 1, NOW()
            WHERE NOT EXISTS (SELECT 1 FROM app_module WHERE slug = 'agenda')
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM app_module WHERE slug = 'agenda'");
        $this->addSql('ALTER TABLE appointment_history DROP FOREIGN KEY FK_APPOINTMENT_HISTORY_APPOINTMENT');
        $this->addSql('ALTER TABLE appointment_history DROP FOREIGN KEY FK_APPOINTMENT_HISTORY_CREATED_BY');
        $this->addSql('ALTER TABLE appointment_history DROP FOREIGN KEY FK_APPOINTMENT_HISTORY_UPDATED_BY');
        $this->addSql('ALTER TABLE appointment_participant DROP FOREIGN KEY FK_APPOINTMENT_PARTICIPANT_APPOINTMENT');
        $this->addSql('ALTER TABLE appointment_participant DROP FOREIGN KEY FK_APPOINTMENT_PARTICIPANT_USER');
        $this->addSql('ALTER TABLE appointment_participant DROP FOREIGN KEY FK_APPOINTMENT_PARTICIPANT_CREATED_BY');
        $this->addSql('ALTER TABLE appointment_participant DROP FOREIGN KEY FK_APPOINTMENT_PARTICIPANT_UPDATED_BY');
        $this->addSql('ALTER TABLE appointment DROP FOREIGN KEY FK_APPOINTMENT_CREATED_BY');
        $this->addSql('ALTER TABLE appointment DROP FOREIGN KEY FK_APPOINTMENT_UPDATED_BY');
        $this->addSql('ALTER TABLE appointment DROP FOREIGN KEY FK_APPOINTMENT_DELETED_BY');
        $this->addSql('DROP TABLE appointment_history');
        $this->addSql('DROP TABLE appointment_participant');
        $this->addSql('DROP TABLE appointment');
    }
}
