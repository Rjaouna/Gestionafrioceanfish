<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260616124500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Links maintenance contracts and interventions to intervenants and updates the maintenance entry point.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE maintenance_contract ADD intervenant_id INT DEFAULT NULL');
        $this->addSql('CREATE INDEX idx_maintenance_contract_intervenant ON maintenance_contract (intervenant_id)');
        $this->addSql('ALTER TABLE maintenance_contract ADD CONSTRAINT FK_MAINTENANCE_CONTRACT_INTERVENANT FOREIGN KEY (intervenant_id) REFERENCES intervenant (id) ON DELETE SET NULL');

        $this->addSql('ALTER TABLE intervention ADD intervenant_id INT DEFAULT NULL');
        $this->addSql('CREATE INDEX idx_intervention_intervenant ON intervention (intervenant_id)');
        $this->addSql('ALTER TABLE intervention ADD CONSTRAINT FK_INTERVENTION_INTERVENANT FOREIGN KEY (intervenant_id) REFERENCES intervenant (id) ON DELETE SET NULL');

        $this->addSql(<<<'SQL'
            UPDATE app_module
            SET route_name = 'app_maintenance_intervenant_index',
                description = 'Intervenants, contrats et interventions de maintenance.'
            WHERE slug = 'maintenance'
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            UPDATE app_module
            SET route_name = 'app_maintenance_intervention_index',
                description = 'Interventions, intervenants et contrats de maintenance.'
            WHERE slug = 'maintenance'
        SQL);
        $this->addSql('ALTER TABLE intervention DROP FOREIGN KEY FK_INTERVENTION_INTERVENANT');
        $this->addSql('DROP INDEX idx_intervention_intervenant ON intervention');
        $this->addSql('ALTER TABLE intervention DROP intervenant_id');

        $this->addSql('ALTER TABLE maintenance_contract DROP FOREIGN KEY FK_MAINTENANCE_CONTRACT_INTERVENANT');
        $this->addSql('DROP INDEX idx_maintenance_contract_intervenant ON maintenance_contract');
        $this->addSql('ALTER TABLE maintenance_contract DROP intervenant_id');
    }
}
