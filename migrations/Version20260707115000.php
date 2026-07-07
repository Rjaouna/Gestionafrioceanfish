<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260707115000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds interim personnel payment tracking.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE interim_payment (
              id INT AUTO_INCREMENT NOT NULL,
              worker_id INT DEFAULT NULL,
              created_by_id INT DEFAULT NULL,
              updated_by_id INT DEFAULT NULL,
              payment_date DATE NOT NULL,
              period_from DATE DEFAULT NULL,
              period_to DATE DEFAULT NULL,
              amount NUMERIC(12, 2) NOT NULL,
              payment_method VARCHAR(40) NOT NULL,
              status VARCHAR(30) NOT NULL,
              reference VARCHAR(120) DEFAULT NULL,
              note LONGTEXT DEFAULT NULL,
              created_at DATETIME NOT NULL,
              updated_at DATETIME DEFAULT NULL,
              INDEX idx_interim_payment_worker (worker_id),
              INDEX idx_interim_payment_date (payment_date),
              INDEX idx_interim_payment_period (period_from, period_to),
              INDEX idx_interim_payment_status (status),
              INDEX idx_interim_payment_method (payment_method),
              INDEX idx_interim_payment_created_by (created_by_id),
              INDEX idx_interim_payment_updated_by (updated_by_id),
              PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);

        $this->addSql('ALTER TABLE interim_payment ADD CONSTRAINT FK_INTERIM_PAYMENT_WORKER FOREIGN KEY (worker_id) REFERENCES interim_worker (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE interim_payment ADD CONSTRAINT FK_INTERIM_PAYMENT_CREATED_BY FOREIGN KEY (created_by_id) REFERENCES app_user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE interim_payment ADD CONSTRAINT FK_INTERIM_PAYMENT_UPDATED_BY FOREIGN KEY (updated_by_id) REFERENCES app_user (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE interim_payment DROP FOREIGN KEY FK_INTERIM_PAYMENT_WORKER');
        $this->addSql('ALTER TABLE interim_payment DROP FOREIGN KEY FK_INTERIM_PAYMENT_CREATED_BY');
        $this->addSql('ALTER TABLE interim_payment DROP FOREIGN KEY FK_INTERIM_PAYMENT_UPDATED_BY');
        $this->addSql('DROP TABLE interim_payment');
    }
}
