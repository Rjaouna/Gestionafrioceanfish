<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260615165000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds one-time public reveal links for password access emails.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE password_reveal_link (
              id INT AUTO_INCREMENT NOT NULL,
              token_hash VARCHAR(64) NOT NULL,
              expires_at DATETIME NOT NULL,
              password_entry_id INT NOT NULL,
              recipient_id INT NOT NULL,
              created_at DATETIME NOT NULL,
              updated_at DATETIME DEFAULT NULL,
              created_by_id INT DEFAULT NULL,
              updated_by_id INT DEFAULT NULL,
              INDEX IDX_PASSWORD_REVEAL_ENTRY (password_entry_id),
              INDEX IDX_PASSWORD_REVEAL_RECIPIENT (recipient_id),
              INDEX IDX_PASSWORD_REVEAL_CREATED_BY (created_by_id),
              INDEX IDX_PASSWORD_REVEAL_UPDATED_BY (updated_by_id),
              UNIQUE INDEX uniq_password_reveal_token_hash (token_hash),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 ENGINE = InnoDB
        SQL);
        $this->addSql('ALTER TABLE password_reveal_link ADD CONSTRAINT FK_PASSWORD_REVEAL_ENTRY FOREIGN KEY (password_entry_id) REFERENCES password_entry (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE password_reveal_link ADD CONSTRAINT FK_PASSWORD_REVEAL_RECIPIENT FOREIGN KEY (recipient_id) REFERENCES app_user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE password_reveal_link ADD CONSTRAINT FK_PASSWORD_REVEAL_CREATED_BY FOREIGN KEY (created_by_id) REFERENCES app_user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE password_reveal_link ADD CONSTRAINT FK_PASSWORD_REVEAL_UPDATED_BY FOREIGN KEY (updated_by_id) REFERENCES app_user (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE password_reveal_link DROP FOREIGN KEY FK_PASSWORD_REVEAL_ENTRY');
        $this->addSql('ALTER TABLE password_reveal_link DROP FOREIGN KEY FK_PASSWORD_REVEAL_RECIPIENT');
        $this->addSql('ALTER TABLE password_reveal_link DROP FOREIGN KEY FK_PASSWORD_REVEAL_CREATED_BY');
        $this->addSql('ALTER TABLE password_reveal_link DROP FOREIGN KEY FK_PASSWORD_REVEAL_UPDATED_BY');
        $this->addSql('DROP TABLE password_reveal_link');
    }
}
