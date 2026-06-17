<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260617122000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds user sharing for expenses.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE expense_share (
            id INT AUTO_INCREMENT NOT NULL,
            expense_id INT NOT NULL,
            user_id INT NOT NULL,
            created_by_id INT DEFAULT NULL,
            updated_by_id INT DEFAULT NULL,
            can_view TINYINT(1) DEFAULT 1 NOT NULL,
            is_active TINYINT(1) DEFAULT 1 NOT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME DEFAULT NULL,
            INDEX idx_expense_share_expense (expense_id),
            INDEX idx_expense_share_user (user_id),
            INDEX idx_expense_share_created_by (created_by_id),
            INDEX idx_expense_share_updated_by (updated_by_id),
            UNIQUE INDEX uniq_expense_share_user (expense_id, user_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE expense_share ADD CONSTRAINT FK_EXPENSE_SHARE_EXPENSE FOREIGN KEY (expense_id) REFERENCES expense (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE expense_share ADD CONSTRAINT FK_EXPENSE_SHARE_USER FOREIGN KEY (user_id) REFERENCES app_user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE expense_share ADD CONSTRAINT FK_EXPENSE_SHARE_CREATED_BY FOREIGN KEY (created_by_id) REFERENCES app_user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE expense_share ADD CONSTRAINT FK_EXPENSE_SHARE_UPDATED_BY FOREIGN KEY (updated_by_id) REFERENCES app_user (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE expense_share DROP FOREIGN KEY FK_EXPENSE_SHARE_EXPENSE');
        $this->addSql('ALTER TABLE expense_share DROP FOREIGN KEY FK_EXPENSE_SHARE_USER');
        $this->addSql('ALTER TABLE expense_share DROP FOREIGN KEY FK_EXPENSE_SHARE_CREATED_BY');
        $this->addSql('ALTER TABLE expense_share DROP FOREIGN KEY FK_EXPENSE_SHARE_UPDATED_BY');
        $this->addSql('DROP TABLE expense_share');
    }
}
