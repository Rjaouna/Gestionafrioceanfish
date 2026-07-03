<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260702123000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds interim worker workflow actions, dated mission-end and do-not-recall decisions.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE interim_worker
              ADD mission_end_reason LONGTEXT DEFAULT NULL,
              ADD mission_ended_at DATETIME DEFAULT NULL,
              ADD do_not_recall_at DATETIME DEFAULT NULL,
              ADD do_not_recall_reason LONGTEXT DEFAULT NULL,
              ADD last_status_changed_at DATETIME DEFAULT NULL
        SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE interim_worker_action (
              id INT AUTO_INCREMENT NOT NULL,
              worker_id INT NOT NULL,
              performed_by_id INT DEFAULT NULL,
              created_by_id INT DEFAULT NULL,
              updated_by_id INT DEFAULT NULL,
              action_type VARCHAR(40) NOT NULL,
              previous_status VARCHAR(40) DEFAULT NULL,
              new_status VARCHAR(40) DEFAULT NULL,
              reason LONGTEXT DEFAULT NULL,
              action_at DATETIME NOT NULL,
              created_at DATETIME NOT NULL,
              updated_at DATETIME DEFAULT NULL,
              INDEX idx_interim_worker_action_worker (worker_id),
              INDEX idx_interim_worker_action_type (action_type),
              INDEX idx_interim_worker_action_action_at (action_at),
              INDEX idx_interim_worker_action_performed_by (performed_by_id),
              INDEX idx_interim_worker_action_created_by (created_by_id),
              INDEX idx_interim_worker_action_updated_by (updated_by_id),
              PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);

        $this->addSql('ALTER TABLE interim_worker_action ADD CONSTRAINT FK_INTERIM_WORKER_ACTION_WORKER FOREIGN KEY (worker_id) REFERENCES interim_worker (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE interim_worker_action ADD CONSTRAINT FK_INTERIM_WORKER_ACTION_PERFORMED_BY FOREIGN KEY (performed_by_id) REFERENCES app_user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE interim_worker_action ADD CONSTRAINT FK_INTERIM_WORKER_ACTION_CREATED_BY FOREIGN KEY (created_by_id) REFERENCES app_user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE interim_worker_action ADD CONSTRAINT FK_INTERIM_WORKER_ACTION_UPDATED_BY FOREIGN KEY (updated_by_id) REFERENCES app_user (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE interim_worker_action DROP FOREIGN KEY FK_INTERIM_WORKER_ACTION_WORKER');
        $this->addSql('ALTER TABLE interim_worker_action DROP FOREIGN KEY FK_INTERIM_WORKER_ACTION_PERFORMED_BY');
        $this->addSql('ALTER TABLE interim_worker_action DROP FOREIGN KEY FK_INTERIM_WORKER_ACTION_CREATED_BY');
        $this->addSql('ALTER TABLE interim_worker_action DROP FOREIGN KEY FK_INTERIM_WORKER_ACTION_UPDATED_BY');
        $this->addSql('DROP TABLE interim_worker_action');
        $this->addSql(<<<'SQL'
            ALTER TABLE interim_worker
              DROP mission_end_reason,
              DROP mission_ended_at,
              DROP do_not_recall_at,
              DROP do_not_recall_reason,
              DROP last_status_changed_at
        SQL);
    }
}
