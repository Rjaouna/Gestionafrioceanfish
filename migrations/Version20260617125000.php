<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260617125000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Backfills explicit creator shares for contacts, documents and expenses.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            INSERT INTO contact_share (
                can_view,
                is_active,
                created_at,
                updated_at,
                contact_id,
                user_id,
                created_by_id,
                updated_by_id
            )
            SELECT
                1,
                1,
                CURRENT_TIMESTAMP,
                NULL,
                c.id,
                c.created_by_id,
                NULL,
                NULL
            FROM contact c
            WHERE c.created_by_id IS NOT NULL
              AND NOT EXISTS (
                  SELECT 1
                  FROM contact_share cs
                  WHERE cs.contact_id = c.id
                    AND cs.user_id = c.created_by_id
              )
        SQL);

        $this->addSql(<<<'SQL'
            INSERT INTO document_share (
                can_view,
                can_download,
                email_sent_at,
                expires_at,
                is_active,
                created_at,
                updated_at,
                document_id,
                user_id,
                created_by_id,
                updated_by_id
            )
            SELECT
                1,
                1,
                NULL,
                NULL,
                1,
                CURRENT_TIMESTAMP,
                NULL,
                d.id,
                d.created_by_id,
                NULL,
                NULL
            FROM document d
            WHERE d.created_by_id IS NOT NULL
              AND NOT EXISTS (
                  SELECT 1
                  FROM document_share ds
                  WHERE ds.document_id = d.id
                    AND ds.user_id = d.created_by_id
              )
        SQL);

        $this->addSql(<<<'SQL'
            INSERT INTO expense_share (
                can_view,
                is_active,
                created_at,
                updated_at,
                expense_id,
                user_id,
                created_by_id,
                updated_by_id
            )
            SELECT
                1,
                1,
                CURRENT_TIMESTAMP,
                NULL,
                e.id,
                e.created_by_id,
                NULL,
                NULL
            FROM expense e
            WHERE e.created_by_id IS NOT NULL
              AND NOT EXISTS (
                  SELECT 1
                  FROM expense_share es
                  WHERE es.expense_id = e.id
                    AND es.user_id = e.created_by_id
              )
        SQL);
    }

    public function down(Schema $schema): void
    {
        // Data migration only. Existing shares are intentionally preserved.
    }
}
