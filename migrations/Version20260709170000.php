<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260709170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds cash fund tracking for paid expenses.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE cash_fund_transaction (
              id INT AUTO_INCREMENT NOT NULL,
              expense_id INT DEFAULT NULL,
              created_by_id INT DEFAULT NULL,
              updated_by_id INT DEFAULT NULL,
              deleted_by_id INT DEFAULT NULL,
              reference VARCHAR(80) NOT NULL,
              movement_date DATE NOT NULL,
              type VARCHAR(40) NOT NULL,
              amount NUMERIC(12, 2) NOT NULL,
              payment_method VARCHAR(40) NOT NULL,
              source_name VARCHAR(180) DEFAULT NULL,
              notes LONGTEXT DEFAULT NULL,
              is_deleted TINYINT DEFAULT 0 NOT NULL,
              deleted_at DATETIME DEFAULT NULL,
              delete_reason LONGTEXT DEFAULT NULL,
              created_at DATETIME NOT NULL,
              updated_at DATETIME DEFAULT NULL,
              INDEX idx_cash_fund_expense (expense_id),
              INDEX idx_cash_fund_created_by (created_by_id),
              INDEX idx_cash_fund_updated_by (updated_by_id),
              INDEX idx_cash_fund_deleted_by (deleted_by_id),
              INDEX idx_cash_fund_type (type),
              INDEX idx_cash_fund_date (movement_date),
              UNIQUE INDEX uniq_cash_fund_reference (reference),
              PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 ENGINE = InnoDB
        SQL);

        $this->addSql('ALTER TABLE cash_fund_transaction ADD CONSTRAINT FK_CASH_FUND_EXPENSE FOREIGN KEY (expense_id) REFERENCES expense (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE cash_fund_transaction ADD CONSTRAINT FK_CASH_FUND_CREATED_BY FOREIGN KEY (created_by_id) REFERENCES app_user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE cash_fund_transaction ADD CONSTRAINT FK_CASH_FUND_UPDATED_BY FOREIGN KEY (updated_by_id) REFERENCES app_user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE cash_fund_transaction ADD CONSTRAINT FK_CASH_FUND_DELETED_BY FOREIGN KEY (deleted_by_id) REFERENCES app_user (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE cash_fund_transaction DROP FOREIGN KEY FK_CASH_FUND_DELETED_BY');
        $this->addSql('ALTER TABLE cash_fund_transaction DROP FOREIGN KEY FK_CASH_FUND_UPDATED_BY');
        $this->addSql('ALTER TABLE cash_fund_transaction DROP FOREIGN KEY FK_CASH_FUND_CREATED_BY');
        $this->addSql('ALTER TABLE cash_fund_transaction DROP FOREIGN KEY FK_CASH_FUND_EXPENSE');
        $this->addSql('DROP TABLE cash_fund_transaction');
    }
}
