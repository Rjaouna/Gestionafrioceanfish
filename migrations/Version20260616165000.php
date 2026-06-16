<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260616165000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds the expenses module with categories, workflow fields and private documents.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE expense_category (
              id INT AUTO_INCREMENT NOT NULL,
              name VARCHAR(140) NOT NULL,
              slug VARCHAR(160) NOT NULL,
              description LONGTEXT DEFAULT NULL,
              icon VARCHAR(80) NOT NULL,
              color VARCHAR(40) DEFAULT NULL,
              is_active TINYINT DEFAULT 1 NOT NULL,
              created_at DATETIME NOT NULL,
              updated_at DATETIME DEFAULT NULL,
              created_by_id INT DEFAULT NULL,
              updated_by_id INT DEFAULT NULL,
              INDEX IDX_EXPENSE_CATEGORY_CREATED_BY (created_by_id),
              INDEX IDX_EXPENSE_CATEGORY_UPDATED_BY (updated_by_id),
              UNIQUE INDEX uniq_expense_category_slug (slug),
              PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 ENGINE = InnoDB
        SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE expense (
              id INT AUTO_INCREMENT NOT NULL,
              category_id INT DEFAULT NULL,
              paid_by_id INT DEFAULT NULL,
              validated_by_id INT DEFAULT NULL,
              refused_by_id INT DEFAULT NULL,
              created_by_id INT DEFAULT NULL,
              updated_by_id INT DEFAULT NULL,
              title VARCHAR(180) NOT NULL,
              reference VARCHAR(80) NOT NULL,
              expense_date DATE NOT NULL,
              amount_ht NUMERIC(12, 2) NOT NULL,
              vat_rate NUMERIC(5, 2) NOT NULL,
              vat_amount NUMERIC(12, 2) NOT NULL,
              amount_ttc NUMERIC(12, 2) NOT NULL,
              payment_method VARCHAR(60) NOT NULL,
              supplier_name VARCHAR(180) NOT NULL,
              supplier_email VARCHAR(180) DEFAULT NULL,
              supplier_phone VARCHAR(40) DEFAULT NULL,
              invoice_number VARCHAR(120) DEFAULT NULL,
              description LONGTEXT DEFAULT NULL,
              status VARCHAR(40) NOT NULL,
              paid_at DATETIME DEFAULT NULL,
              validated_at DATETIME DEFAULT NULL,
              refused_reason LONGTEXT DEFAULT NULL,
              is_active TINYINT DEFAULT 1 NOT NULL,
              INDEX IDX_EXPENSE_CATEGORY (category_id),
              INDEX IDX_EXPENSE_PAID_BY (paid_by_id),
              INDEX IDX_EXPENSE_VALIDATED_BY (validated_by_id),
              INDEX IDX_EXPENSE_REFUSED_BY (refused_by_id),
              INDEX idx_expense_created_by (created_by_id),
              INDEX IDX_EXPENSE_UPDATED_BY (updated_by_id),
              INDEX idx_expense_status (status),
              INDEX idx_expense_date (expense_date),
              UNIQUE INDEX uniq_expense_reference (reference),
              PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 ENGINE = InnoDB
        SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE expense_document (
              id INT AUTO_INCREMENT NOT NULL,
              expense_id INT NOT NULL,
              original_file_name VARCHAR(255) NOT NULL,
              file_name VARCHAR(255) NOT NULL,
              mime_type VARCHAR(160) NOT NULL,
              file_size INT NOT NULL,
              document_type VARCHAR(40) NOT NULL,
              is_active TINYINT DEFAULT 1 NOT NULL,
              created_at DATETIME NOT NULL,
              updated_at DATETIME DEFAULT NULL,
              created_by_id INT DEFAULT NULL,
              updated_by_id INT DEFAULT NULL,
              INDEX idx_expense_document_expense (expense_id),
              INDEX IDX_EXPENSE_DOCUMENT_CREATED_BY (created_by_id),
              INDEX IDX_EXPENSE_DOCUMENT_UPDATED_BY (updated_by_id),
              PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 ENGINE = InnoDB
        SQL);

        $this->addSql('ALTER TABLE expense_category ADD CONSTRAINT FK_EXPENSE_CATEGORY_CREATED_BY FOREIGN KEY (created_by_id) REFERENCES app_user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE expense_category ADD CONSTRAINT FK_EXPENSE_CATEGORY_UPDATED_BY FOREIGN KEY (updated_by_id) REFERENCES app_user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE expense ADD CONSTRAINT FK_EXPENSE_CATEGORY FOREIGN KEY (category_id) REFERENCES expense_category (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE expense ADD CONSTRAINT FK_EXPENSE_PAID_BY FOREIGN KEY (paid_by_id) REFERENCES app_user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE expense ADD CONSTRAINT FK_EXPENSE_VALIDATED_BY FOREIGN KEY (validated_by_id) REFERENCES app_user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE expense ADD CONSTRAINT FK_EXPENSE_REFUSED_BY FOREIGN KEY (refused_by_id) REFERENCES app_user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE expense ADD CONSTRAINT FK_EXPENSE_CREATED_BY FOREIGN KEY (created_by_id) REFERENCES app_user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE expense ADD CONSTRAINT FK_EXPENSE_UPDATED_BY FOREIGN KEY (updated_by_id) REFERENCES app_user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE expense_document ADD CONSTRAINT FK_EXPENSE_DOCUMENT_EXPENSE FOREIGN KEY (expense_id) REFERENCES expense (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE expense_document ADD CONSTRAINT FK_EXPENSE_DOCUMENT_CREATED_BY FOREIGN KEY (created_by_id) REFERENCES app_user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE expense_document ADD CONSTRAINT FK_EXPENSE_DOCUMENT_UPDATED_BY FOREIGN KEY (updated_by_id) REFERENCES app_user (id) ON DELETE SET NULL');

        $this->addSql(<<<'SQL'
            INSERT INTO app_module (name, slug, description, icon, route_name, is_active, created_at)
            SELECT 'Dépenses', 'expenses', 'Gestion financière des dépenses, justificatifs et validations.', 'bi-cash-coin', 'app_expense_index', 1, NOW()
            WHERE NOT EXISTS (SELECT 1 FROM app_module WHERE slug = 'expenses')
        SQL);

        foreach ($this->defaultCategories() as $category) {
            $this->addSql(sprintf(
                "INSERT INTO expense_category (name, slug, description, icon, color, is_active, created_at) SELECT %s, %s, %s, %s, %s, 1, NOW() WHERE NOT EXISTS (SELECT 1 FROM expense_category WHERE slug = %s)",
                $this->quote($category[0]),
                $this->quote($category[1]),
                $this->quote($category[2]),
                $this->quote($category[3]),
                $this->quote($category[4]),
                $this->quote($category[1]),
            ));
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM app_module WHERE slug = 'expenses'");
        $this->addSql('ALTER TABLE expense_document DROP FOREIGN KEY FK_EXPENSE_DOCUMENT_UPDATED_BY');
        $this->addSql('ALTER TABLE expense_document DROP FOREIGN KEY FK_EXPENSE_DOCUMENT_CREATED_BY');
        $this->addSql('ALTER TABLE expense_document DROP FOREIGN KEY FK_EXPENSE_DOCUMENT_EXPENSE');
        $this->addSql('ALTER TABLE expense DROP FOREIGN KEY FK_EXPENSE_UPDATED_BY');
        $this->addSql('ALTER TABLE expense DROP FOREIGN KEY FK_EXPENSE_CREATED_BY');
        $this->addSql('ALTER TABLE expense DROP FOREIGN KEY FK_EXPENSE_REFUSED_BY');
        $this->addSql('ALTER TABLE expense DROP FOREIGN KEY FK_EXPENSE_VALIDATED_BY');
        $this->addSql('ALTER TABLE expense DROP FOREIGN KEY FK_EXPENSE_PAID_BY');
        $this->addSql('ALTER TABLE expense DROP FOREIGN KEY FK_EXPENSE_CATEGORY');
        $this->addSql('ALTER TABLE expense_category DROP FOREIGN KEY FK_EXPENSE_CATEGORY_UPDATED_BY');
        $this->addSql('ALTER TABLE expense_category DROP FOREIGN KEY FK_EXPENSE_CATEGORY_CREATED_BY');
        $this->addSql('DROP TABLE expense_document');
        $this->addSql('DROP TABLE expense');
        $this->addSql('DROP TABLE expense_category');
    }

    /** @return list<array{0: string, 1: string, 2: string, 3: string, 4: string}> */
    private function defaultCategories(): array
    {
        return [
            ['Carburant', 'carburant', 'Carburant et frais de déplacement.', 'bi-fuel-pump', 'primary'],
            ['Péage', 'peage', 'Frais de péage et stationnement.', 'bi-signpost-2', 'secondary'],
            ['Fournitures', 'fournitures', 'Fournitures administratives et consommables.', 'bi-pencil-square', 'info'],
            ['Matériel', 'materiel', 'Achat ou renouvellement de matériel.', 'bi-tools', 'warning'],
            ['Abonnements', 'abonnements', 'Logiciels, services et abonnements.', 'bi-repeat', 'primary'],
            ['Loyer', 'loyer', 'Loyers et charges locatives.', 'bi-building', 'secondary'],
            ['Prestataire', 'prestataire', 'Prestataires et sous-traitance.', 'bi-person-workspace', 'success'],
            ['Salaire', 'salaire', 'Salaires et charges associées.', 'bi-people', 'success'],
            ['Frais bancaires', 'frais-bancaires', 'Frais de banque et commissions.', 'bi-bank', 'danger'],
            ['Assurance', 'assurance', 'Assurances professionnelles.', 'bi-shield-check', 'info'],
            ['Entretien véhicule', 'entretien-vehicule', 'Maintenance et entretien des véhicules.', 'bi-car-front', 'warning'],
            ['Autre', 'autre', 'Dépenses diverses.', 'bi-three-dots', 'dark'],
        ];
    }

    private function quote(string $value): string
    {
        return $this->connection->quote($value);
    }
}
