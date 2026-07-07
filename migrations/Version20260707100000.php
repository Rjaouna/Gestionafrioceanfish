<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260707100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds passport identity fields to interim worker records.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE interim_worker
              ADD passport_number VARCHAR(40) DEFAULT NULL,
              ADD passport_issue_country VARCHAR(3) DEFAULT NULL,
              ADD nationality VARCHAR(100) DEFAULT NULL,
              ADD gender VARCHAR(10) DEFAULT NULL,
              ADD passport_issued_at DATE DEFAULT NULL,
              ADD passport_expires_at DATE DEFAULT NULL,
              ADD passport_mrz LONGTEXT DEFAULT NULL
        SQL);
        $this->addSql('CREATE INDEX idx_interim_worker_passport_number ON interim_worker (passport_number)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_interim_worker_passport_number ON interim_worker');
        $this->addSql(<<<'SQL'
            ALTER TABLE interim_worker
              DROP passport_number,
              DROP passport_issue_country,
              DROP nationality,
              DROP gender,
              DROP passport_issued_at,
              DROP passport_expires_at,
              DROP passport_mrz
        SQL);
    }
}
