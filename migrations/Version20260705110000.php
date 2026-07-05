<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260705110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds shipment tracking to fish reception workflow.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE fish_reception ADD expedited_by_id INT DEFAULT NULL, ADD expedited_at DATETIME DEFAULT NULL');
        $this->addSql('CREATE INDEX idx_fish_reception_expedited_by ON fish_reception (expedited_by_id)');
        $this->addSql('ALTER TABLE fish_reception ADD CONSTRAINT FK_FISH_RECEPTION_EXPEDITED_BY FOREIGN KEY (expedited_by_id) REFERENCES app_user (id) ON DELETE SET NULL');
        $this->addSql("UPDATE app_module SET description = 'Reception poisson, traitement, emballage, congelation, stockage et expedition.', updated_at = NOW() WHERE slug = 'receptions'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE fish_reception DROP FOREIGN KEY FK_FISH_RECEPTION_EXPEDITED_BY');
        $this->addSql('DROP INDEX idx_fish_reception_expedited_by ON fish_reception');
        $this->addSql('ALTER TABLE fish_reception DROP expedited_by_id, DROP expedited_at');
    }
}
