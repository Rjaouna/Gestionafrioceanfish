<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260615134232 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Creates the MySQL schema for users, password sharing and module access.';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE app_module (
              id INT AUTO_INCREMENT NOT NULL,
              name VARCHAR(120) NOT NULL,
              slug VARCHAR(120) NOT NULL,
              description LONGTEXT DEFAULT NULL,
              icon VARCHAR(80) NOT NULL,
              route_name VARCHAR(180) NOT NULL,
              is_active TINYINT DEFAULT 1 NOT NULL,
              created_at DATETIME NOT NULL,
              updated_at DATETIME DEFAULT NULL,
              created_by_id INT DEFAULT NULL,
              updated_by_id INT DEFAULT NULL,
              INDEX IDX_E9274BA8B03A8386 (created_by_id),
              INDEX IDX_E9274BA8896DBBDE (updated_by_id),
              UNIQUE INDEX uniq_module_slug (slug),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE app_user (
              id INT AUTO_INCREMENT NOT NULL,
              email VARCHAR(180) NOT NULL,
              roles JSON NOT NULL,
              password VARCHAR(255) NOT NULL,
              first_name VARCHAR(100) DEFAULT NULL,
              last_name VARCHAR(100) DEFAULT NULL,
              is_active TINYINT DEFAULT 1 NOT NULL,
              created_at DATETIME NOT NULL,
              updated_at DATETIME DEFAULT NULL,
              created_by_id INT DEFAULT NULL,
              updated_by_id INT DEFAULT NULL,
              INDEX IDX_88BDF3E9B03A8386 (created_by_id),
              INDEX IDX_88BDF3E9896DBBDE (updated_by_id),
              UNIQUE INDEX uniq_user_email (email),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE password_entry (
              id INT AUTO_INCREMENT NOT NULL,
              name VARCHAR(180) NOT NULL,
              login VARCHAR(255) NOT NULL,
              encrypted_password LONGTEXT NOT NULL,
              link VARCHAR(2048) DEFAULT NULL,
              description LONGTEXT DEFAULT NULL,
              created_at DATETIME NOT NULL,
              updated_at DATETIME DEFAULT NULL,
              created_by_id INT DEFAULT NULL,
              updated_by_id INT DEFAULT NULL,
              INDEX IDX_CABD506FB03A8386 (created_by_id),
              INDEX IDX_CABD506F896DBBDE (updated_by_id),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE password_share (
              id INT AUTO_INCREMENT NOT NULL,
              can_view TINYINT DEFAULT 1 NOT NULL,
              can_edit_password TINYINT DEFAULT 0 NOT NULL,
              created_at DATETIME NOT NULL,
              updated_at DATETIME DEFAULT NULL,
              password_entry_id INT NOT NULL,
              user_id INT NOT NULL,
              created_by_id INT DEFAULT NULL,
              updated_by_id INT DEFAULT NULL,
              INDEX IDX_E9A50452AA1DB82 (password_entry_id),
              INDEX IDX_E9A5045A76ED395 (user_id),
              INDEX IDX_E9A5045B03A8386 (created_by_id),
              INDEX IDX_E9A5045896DBBDE (updated_by_id),
              UNIQUE INDEX uniq_password_share_user (password_entry_id, user_id),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE user_module_access (
              id INT AUTO_INCREMENT NOT NULL,
              created_at DATETIME NOT NULL,
              updated_at DATETIME DEFAULT NULL,
              user_id INT NOT NULL,
              module_id INT NOT NULL,
              created_by_id INT DEFAULT NULL,
              updated_by_id INT DEFAULT NULL,
              INDEX IDX_42E4C62BA76ED395 (user_id),
              INDEX IDX_42E4C62BAFC2B591 (module_id),
              INDEX IDX_42E4C62BB03A8386 (created_by_id),
              INDEX IDX_42E4C62B896DBBDE (updated_by_id),
              UNIQUE INDEX uniq_user_module_access (user_id, module_id),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE messenger_messages (
              id BIGINT AUTO_INCREMENT NOT NULL,
              body LONGTEXT NOT NULL,
              headers LONGTEXT NOT NULL,
              queue_name VARCHAR(190) NOT NULL,
              created_at DATETIME NOT NULL,
              available_at DATETIME NOT NULL,
              delivered_at DATETIME DEFAULT NULL,
              INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 (
                queue_name, available_at, delivered_at,
                id
              ),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              app_module
            ADD
              CONSTRAINT FK_E9274BA8B03A8386 FOREIGN KEY (created_by_id) REFERENCES app_user (id) ON DELETE
            SET
              NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              app_module
            ADD
              CONSTRAINT FK_E9274BA8896DBBDE FOREIGN KEY (updated_by_id) REFERENCES app_user (id) ON DELETE
            SET
              NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              app_user
            ADD
              CONSTRAINT FK_88BDF3E9B03A8386 FOREIGN KEY (created_by_id) REFERENCES app_user (id) ON DELETE
            SET
              NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              app_user
            ADD
              CONSTRAINT FK_88BDF3E9896DBBDE FOREIGN KEY (updated_by_id) REFERENCES app_user (id) ON DELETE
            SET
              NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              password_entry
            ADD
              CONSTRAINT FK_CABD506FB03A8386 FOREIGN KEY (created_by_id) REFERENCES app_user (id) ON DELETE
            SET
              NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              password_entry
            ADD
              CONSTRAINT FK_CABD506F896DBBDE FOREIGN KEY (updated_by_id) REFERENCES app_user (id) ON DELETE
            SET
              NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              password_share
            ADD
              CONSTRAINT FK_E9A50452AA1DB82 FOREIGN KEY (password_entry_id) REFERENCES password_entry (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              password_share
            ADD
              CONSTRAINT FK_E9A5045A76ED395 FOREIGN KEY (user_id) REFERENCES app_user (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              password_share
            ADD
              CONSTRAINT FK_E9A5045B03A8386 FOREIGN KEY (created_by_id) REFERENCES app_user (id) ON DELETE
            SET
              NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              password_share
            ADD
              CONSTRAINT FK_E9A5045896DBBDE FOREIGN KEY (updated_by_id) REFERENCES app_user (id) ON DELETE
            SET
              NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              user_module_access
            ADD
              CONSTRAINT FK_42E4C62BA76ED395 FOREIGN KEY (user_id) REFERENCES app_user (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              user_module_access
            ADD
              CONSTRAINT FK_42E4C62BAFC2B591 FOREIGN KEY (module_id) REFERENCES app_module (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              user_module_access
            ADD
              CONSTRAINT FK_42E4C62BB03A8386 FOREIGN KEY (created_by_id) REFERENCES app_user (id) ON DELETE
            SET
              NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              user_module_access
            ADD
              CONSTRAINT FK_42E4C62B896DBBDE FOREIGN KEY (updated_by_id) REFERENCES app_user (id) ON DELETE
            SET
              NULL
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE app_module DROP FOREIGN KEY FK_E9274BA8B03A8386');
        $this->addSql('ALTER TABLE app_module DROP FOREIGN KEY FK_E9274BA8896DBBDE');
        $this->addSql('ALTER TABLE app_user DROP FOREIGN KEY FK_88BDF3E9B03A8386');
        $this->addSql('ALTER TABLE app_user DROP FOREIGN KEY FK_88BDF3E9896DBBDE');
        $this->addSql('ALTER TABLE password_entry DROP FOREIGN KEY FK_CABD506FB03A8386');
        $this->addSql('ALTER TABLE password_entry DROP FOREIGN KEY FK_CABD506F896DBBDE');
        $this->addSql('ALTER TABLE password_share DROP FOREIGN KEY FK_E9A50452AA1DB82');
        $this->addSql('ALTER TABLE password_share DROP FOREIGN KEY FK_E9A5045A76ED395');
        $this->addSql('ALTER TABLE password_share DROP FOREIGN KEY FK_E9A5045B03A8386');
        $this->addSql('ALTER TABLE password_share DROP FOREIGN KEY FK_E9A5045896DBBDE');
        $this->addSql('ALTER TABLE user_module_access DROP FOREIGN KEY FK_42E4C62BA76ED395');
        $this->addSql('ALTER TABLE user_module_access DROP FOREIGN KEY FK_42E4C62BAFC2B591');
        $this->addSql('ALTER TABLE user_module_access DROP FOREIGN KEY FK_42E4C62BB03A8386');
        $this->addSql('ALTER TABLE user_module_access DROP FOREIGN KEY FK_42E4C62B896DBBDE');
        $this->addSql('DROP TABLE app_module');
        $this->addSql('DROP TABLE app_user');
        $this->addSql('DROP TABLE password_entry');
        $this->addSql('DROP TABLE password_share');
        $this->addSql('DROP TABLE user_module_access');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
