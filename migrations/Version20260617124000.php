<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260617124000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Backfills explicit password shares for existing password creators.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            INSERT INTO password_share (
                can_view,
                can_edit_password,
                created_at,
                updated_at,
                password_entry_id,
                user_id,
                created_by_id,
                updated_by_id
            )
            SELECT
                1,
                1,
                CURRENT_TIMESTAMP,
                NULL,
                p.id,
                p.created_by_id,
                NULL,
                NULL
            FROM password_entry p
            WHERE p.created_by_id IS NOT NULL
              AND NOT EXISTS (
                  SELECT 1
                  FROM password_share ps
                  WHERE ps.password_entry_id = p.id
                    AND ps.user_id = p.created_by_id
              )
        SQL);
    }

    public function down(Schema $schema): void
    {
        // Data migration only. Existing shares are intentionally preserved.
    }
}
