<?php

namespace App\Command;

use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:fish-yield-study:install',
    description: 'Installe la table et le module Etudes rendement poisson si la migration n est pas passee.',
)]
final class InstallFishYieldStudyCommand extends Command
{
    private const MIGRATION_VERSION = 'DoctrineMigrations\\Version20260708120000';

    public function __construct(private readonly Connection $connection)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Installation Etudes rendement poisson');

        try {
            if (!$this->tableExists('fish_yield_study')) {
                $this->createTable();
                $io->success('Table fish_yield_study creee.');
            } else {
                $io->note('La table fish_yield_study existe deja.');
            }

            $this->installModule();
            $this->markMigrationExecuted();
            $io->success('Module Etudes rendement poisson installe.');
        } catch (\Throwable $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    private function tableExists(string $tableName): bool
    {
        return (bool) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :table',
            ['table' => $tableName],
        );
    }

    private function createTable(): void
    {
        $this->connection->executeStatement(<<<'SQL'
            CREATE TABLE fish_yield_study (
              id INT AUTO_INCREMENT NOT NULL,
              created_by_id INT DEFAULT NULL,
              updated_by_id INT DEFAULT NULL,
              deleted_by_id INT DEFAULT NULL,
              reference VARCHAR(80) NOT NULL,
              study_date DATE NOT NULL,
              client_name VARCHAR(180) DEFAULT NULL,
              species_name VARCHAR(180) NOT NULL,
              has_mixed_fish TINYINT(1) NOT NULL DEFAULT 0,
              mixed_fish_name VARCHAR(180) DEFAULT NULL,
              raw_box_weight NUMERIC(12, 3) NOT NULL DEFAULT '0.000',
              thawed_box_weight NUMERIC(12, 3) NOT NULL DEFAULT '0.000',
              pieces_per_kg NUMERIC(10, 2) NOT NULL DEFAULT '0.00',
              finished_product_weight NUMERIC(12, 3) NOT NULL DEFAULT '0.000',
              waste_weight NUMERIC(12, 3) NOT NULL DEFAULT '0.000',
              loss_weight NUMERIC(12, 3) NOT NULL DEFAULT '0.000',
              container_weight NUMERIC(12, 3) NOT NULL DEFAULT '0.000',
              operator_name VARCHAR(150) DEFAULT NULL,
              observations LONGTEXT DEFAULT NULL,
              is_deleted TINYINT(1) NOT NULL DEFAULT 0,
              deleted_at DATETIME DEFAULT NULL,
              delete_reason LONGTEXT DEFAULT NULL,
              created_at DATETIME NOT NULL,
              updated_at DATETIME DEFAULT NULL,
              UNIQUE INDEX uniq_fish_yield_study_reference (reference),
              INDEX idx_fish_yield_study_date (study_date),
              INDEX idx_fish_yield_study_client (client_name),
              INDEX idx_fish_yield_study_species (species_name),
              INDEX idx_fish_yield_study_created_by (created_by_id),
              INDEX idx_fish_yield_study_updated_by (updated_by_id),
              INDEX idx_fish_yield_study_deleted_by (deleted_by_id),
              PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);

        $this->connection->executeStatement('ALTER TABLE fish_yield_study ADD CONSTRAINT FK_FISH_YIELD_STUDY_CREATED_BY FOREIGN KEY (created_by_id) REFERENCES app_user (id) ON DELETE SET NULL');
        $this->connection->executeStatement('ALTER TABLE fish_yield_study ADD CONSTRAINT FK_FISH_YIELD_STUDY_UPDATED_BY FOREIGN KEY (updated_by_id) REFERENCES app_user (id) ON DELETE SET NULL');
        $this->connection->executeStatement('ALTER TABLE fish_yield_study ADD CONSTRAINT FK_FISH_YIELD_STUDY_DELETED_BY FOREIGN KEY (deleted_by_id) REFERENCES app_user (id) ON DELETE SET NULL');
    }

    private function installModule(): void
    {
        $this->connection->executeStatement(<<<'SQL'
            INSERT INTO app_module (name, slug, description, icon, route_name, is_active, created_at, updated_at)
            VALUES ('Etudes rendement poisson', 'etudes-rendement-poisson', 'Essais client sur caisse echantillon, taux eau, rendement filet et estimation conteneur.', 'bi-clipboard2-pulse', 'app_fish_yield_study_index', 1, NOW(), NULL)
            ON DUPLICATE KEY UPDATE
                name = VALUES(name),
                description = VALUES(description),
                icon = VALUES(icon),
                route_name = VALUES(route_name),
                is_active = 1,
                updated_at = NOW()
        SQL);
    }

    private function markMigrationExecuted(): void
    {
        if (!$this->tableExists('doctrine_migration_versions')) {
            return;
        }

        $alreadyMarked = (bool) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM doctrine_migration_versions WHERE version = :version',
            ['version' => self::MIGRATION_VERSION],
        );
        if ($alreadyMarked) {
            return;
        }

        $this->connection->executeStatement(
            'INSERT INTO doctrine_migration_versions (version, executed_at, execution_time) VALUES (:version, NOW(), 0)',
            ['version' => self::MIGRATION_VERSION],
        );
    }
}
