<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260617130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds soft delete columns for trashable business entities.';
    }

    public function up(Schema $schema): void
    {
        foreach ($this->tables() as $table => $index) {
            $this->addSql(sprintf(
                'ALTER TABLE %s ADD is_deleted TINYINT DEFAULT 0 NOT NULL, ADD deleted_at DATETIME DEFAULT NULL, ADD delete_reason LONGTEXT DEFAULT NULL, ADD deleted_by_id INT DEFAULT NULL',
                $table,
            ));
            $this->addSql(sprintf('CREATE INDEX %s ON %s (deleted_by_id)', $index, $table));
            $this->addSql(sprintf(
                'ALTER TABLE %s ADD CONSTRAINT FK_%s_DELETED_BY FOREIGN KEY (deleted_by_id) REFERENCES app_user (id) ON DELETE SET NULL',
                $table,
                strtoupper(str_replace('-', '_', $index)),
            ));
        }
    }

    public function down(Schema $schema): void
    {
        foreach (array_reverse($this->tables(), true) as $table => $index) {
            $this->addSql(sprintf('ALTER TABLE %s DROP FOREIGN KEY FK_%s_DELETED_BY', $table, strtoupper(str_replace('-', '_', $index))));
            $this->addSql(sprintf('DROP INDEX %s ON %s', $index, $table));
            $this->addSql(sprintf('ALTER TABLE %s DROP is_deleted, DROP deleted_at, DROP delete_reason, DROP deleted_by_id', $table));
        }
    }

    /** @return array<string, string> */
    private function tables(): array
    {
        return [
            'contact' => 'IDX_4C62E638C76F1F52',
            'document' => 'IDX_D8698A76C76F1F52',
            'password_entry' => 'IDX_CABD506FC76F1F52',
            'expense' => 'IDX_2D3A8DA6C76F1F52',
            'maintenance_contract' => 'IDX_F7F72C74C76F1F52',
            'intervention' => 'IDX_D11814ABC76F1F52',
            'intervenant' => 'IDX_73D0145CC76F1F52',
        ];
    }
}
