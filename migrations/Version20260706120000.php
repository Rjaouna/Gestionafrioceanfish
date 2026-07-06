<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260706120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds fish reception initial storage movement traceability.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE fish_reception_storage_movement (id INT AUTO_INCREMENT NOT NULL, reception_id INT NOT NULL, created_by_id INT DEFAULT NULL, updated_by_id INT DEFAULT NULL, storage_stage VARCHAR(20) NOT NULL, movement_type VARCHAR(40) NOT NULL, location VARCHAR(120) NOT NULL, quantity_kg NUMERIC(12, 3) NOT NULL, movement_date DATE NOT NULL, movement_time TIME DEFAULT NULL, temperature_chamber NUMERIC(6, 2) DEFAULT NULL, temperature_product NUMERIC(6, 2) DEFAULT NULL, note LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, INDEX idx_fish_storage_movement_reception (reception_id), INDEX idx_fish_storage_movement_stage (storage_stage), INDEX idx_fish_storage_movement_type (movement_type), INDEX idx_fish_storage_movement_location (location), INDEX idx_fish_storage_movement_date (movement_date), INDEX idx_fish_storage_movement_created_by (created_by_id), INDEX IDX_D9ED891D896DBBDE (updated_by_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
        $this->addSql('ALTER TABLE fish_reception_storage_movement ADD CONSTRAINT FK_FISH_STORAGE_MOVEMENT_RECEPTION FOREIGN KEY (reception_id) REFERENCES fish_reception (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE fish_reception_storage_movement ADD CONSTRAINT FK_FISH_STORAGE_MOVEMENT_CREATED_BY FOREIGN KEY (created_by_id) REFERENCES app_user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE fish_reception_storage_movement ADD CONSTRAINT FK_FISH_STORAGE_MOVEMENT_UPDATED_BY FOREIGN KEY (updated_by_id) REFERENCES app_user (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE fish_reception_storage_movement DROP FOREIGN KEY FK_FISH_STORAGE_MOVEMENT_RECEPTION');
        $this->addSql('ALTER TABLE fish_reception_storage_movement DROP FOREIGN KEY FK_FISH_STORAGE_MOVEMENT_CREATED_BY');
        $this->addSql('ALTER TABLE fish_reception_storage_movement DROP FOREIGN KEY FK_FISH_STORAGE_MOVEMENT_UPDATED_BY');
        $this->addSql('DROP TABLE fish_reception_storage_movement');
    }
}
