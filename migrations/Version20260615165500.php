<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260615165500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Normalizes password reveal link index names.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE password_reveal_link RENAME INDEX idx_password_reveal_entry TO IDX_A25A6F422AA1DB82');
        $this->addSql('ALTER TABLE password_reveal_link RENAME INDEX idx_password_reveal_recipient TO IDX_A25A6F42E92F8F78');
        $this->addSql('ALTER TABLE password_reveal_link RENAME INDEX idx_password_reveal_created_by TO IDX_A25A6F42B03A8386');
        $this->addSql('ALTER TABLE password_reveal_link RENAME INDEX idx_password_reveal_updated_by TO IDX_A25A6F42896DBBDE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE password_reveal_link RENAME INDEX IDX_A25A6F422AA1DB82 TO idx_password_reveal_entry');
        $this->addSql('ALTER TABLE password_reveal_link RENAME INDEX IDX_A25A6F42E92F8F78 TO idx_password_reveal_recipient');
        $this->addSql('ALTER TABLE password_reveal_link RENAME INDEX IDX_A25A6F42B03A8386 TO idx_password_reveal_created_by');
        $this->addSql('ALTER TABLE password_reveal_link RENAME INDEX IDX_A25A6F42896DBBDE TO idx_password_reveal_updated_by');
    }
}
