<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260703100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Completes interim worker job sheets with signatures, stricter field sizes and module label.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE interim_worker
              CHANGE position position VARCHAR(100) NOT NULL,
              CHANGE registration_number registration_number VARCHAR(50) NOT NULL,
              CHANGE phone phone VARCHAR(20) NOT NULL,
              CHANGE birth_place birth_place VARCHAR(100) DEFAULT NULL,
              CHANGE cin cin VARCHAR(30) DEFAULT NULL,
              ADD employee_signature VARCHAR(120) DEFAULT NULL,
              ADD manager_signature VARCHAR(120) DEFAULT NULL,
              ADD signature_date DATE DEFAULT NULL
        SQL);

        $this->addSql(<<<'SQL'
            UPDATE app_module
            SET name = 'Fiches intérimaires',
                description = 'Fiches de poste, suivi RH, statuts et documents des intérimaires.'
            WHERE slug = 'interimaires'
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE interim_worker
              CHANGE position position VARCHAR(160) NOT NULL,
              CHANGE registration_number registration_number VARCHAR(80) NOT NULL,
              CHANGE phone phone VARCHAR(40) NOT NULL,
              CHANGE birth_place birth_place VARCHAR(120) DEFAULT NULL,
              CHANGE cin cin VARCHAR(40) DEFAULT NULL,
              DROP employee_signature,
              DROP manager_signature,
              DROP signature_date
        SQL);

        $this->addSql(<<<'SQL'
            UPDATE app_module
            SET name = 'Intérimaires',
                description = 'Fiches de poste, suivi RH et documents des intérimaires.'
            WHERE slug = 'interimaires'
        SQL);
    }
}
