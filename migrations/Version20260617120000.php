<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260617120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds classification and lifecycle fields to documents.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE document ADD category VARCHAR(120) DEFAULT NULL, ADD internal_reference VARCHAR(80) DEFAULT NULL, ADD document_date DATE DEFAULT NULL, ADD issuer VARCHAR(180) DEFAULT NULL, ADD language VARCHAR(80) DEFAULT NULL, ADD status VARCHAR(40) DEFAULT 'actif' NOT NULL, ADD expires_at DATE DEFAULT NULL, ADD tags LONGTEXT DEFAULT NULL, ADD confidentiality_level VARCHAR(40) DEFAULT NULL, ADD version VARCHAR(80) DEFAULT NULL");
        $this->addSql("UPDATE document SET status = 'archivé' WHERE is_active = 0");
        $this->addSql('CREATE UNIQUE INDEX uniq_document_internal_reference ON document (internal_reference)');
        $this->addSql('CREATE INDEX idx_document_category ON document (category)');
        $this->addSql('CREATE INDEX idx_document_status ON document (status)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_document_status ON document');
        $this->addSql('DROP INDEX idx_document_category ON document');
        $this->addSql('DROP INDEX uniq_document_internal_reference ON document');
        $this->addSql('ALTER TABLE document DROP category, DROP internal_reference, DROP document_date, DROP issuer, DROP language, DROP status, DROP expires_at, DROP tags, DROP confidentiality_level, DROP version');
    }
}
