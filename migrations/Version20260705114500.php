<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260705114500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds business treatment start date to fish receptions.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE fish_reception ADD date_debut_traitement DATE DEFAULT NULL');
        $this->addSql('UPDATE fish_reception SET date_debut_traitement = DATE(treatment_started_at) WHERE treatment_started_at IS NOT NULL AND date_debut_traitement IS NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE fish_reception DROP date_debut_traitement');
    }
}
