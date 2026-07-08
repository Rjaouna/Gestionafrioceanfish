<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260708130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Updates default task attendance rates for anchovy cleaning and filet boxing.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            INSERT INTO interim_attendance_rate (code, label, mode, unit_label, amount, active, created_at, updated_at)
            VALUES ('task_cleaning', 'Nettoyage anchois', 'task', '30 kg', 25.00, 1, NOW(), NOW())
            ON DUPLICATE KEY UPDATE
                label = VALUES(label),
                mode = VALUES(mode),
                unit_label = VALUES(unit_label),
                amount = IF(amount <= 0, VALUES(amount), amount),
                active = 1,
                updated_at = NOW()
        SQL);

        $this->addSql(<<<'SQL'
            INSERT INTO interim_attendance_rate (code, label, mode, unit_label, amount, active, created_at, updated_at)
            VALUES ('task_boxing', 'Mise en caisse filets', 'task', 'kg', 2.00, 1, NOW(), NOW())
            ON DUPLICATE KEY UPDATE
                label = VALUES(label),
                mode = VALUES(mode),
                unit_label = VALUES(unit_label),
                amount = IF(amount <= 0, VALUES(amount), amount),
                active = 1,
                updated_at = NOW()
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql("UPDATE interim_attendance_rate SET label = 'Tache nettoyage', unit_label = 'nettoyage', updated_at = NOW() WHERE code = 'task_cleaning'");
        $this->addSql("UPDATE interim_attendance_rate SET label = 'Tache mise en caisse', unit_label = 'caisse', updated_at = NOW() WHERE code = 'task_boxing'");
    }
}
