<?php

namespace App\Repository;

use App\Entity\FishReception;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<FishReception> */
class FishReceptionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FishReception::class);
    }

    /**
     * @param array<string, mixed> $filters
     *
     * @return list<FishReception>
     */
    public function searchWithFilters(array $filters = [], int $page = 1, int $perPage = 15): array
    {
        return $this->buildFilteredQuery($filters)
            ->setFirstResult(max(0, ($page - 1) * $perPage))
            ->setMaxResults($perPage)
            ->getQuery()
            ->getResult();
    }

    /** @param array<string, mixed> $filters */
    public function countWithFilters(array $filters = []): int
    {
        return (int) $this->buildFilteredQuery($filters)
            ->select('COUNT(DISTINCT r.id)')
            ->resetDQLPart('orderBy')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countByReceptionPrefix(string $prefix): int
    {
        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->andWhere('r.numeroReception LIKE :prefix')
            ->setParameter('prefix', $prefix.'%')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countByLotPrefix(string $prefix): int
    {
        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->andWhere('r.numeroLot LIKE :prefix')
            ->setParameter('prefix', $prefix.'%')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /** @return list<FishReception> */
    public function availableForProduction(?FishReception $currentReception = null): array
    {
        $builder = $this->createQueryBuilder('r')
            ->andWhere('r.isDeleted = false')
            ->andWhere('r.statut NOT IN (:locked)')
            ->setParameter('locked', [FishReception::STATUS_DRAFT, FishReception::STATUS_BLOCKED, FishReception::STATUS_CLOSED])
            ->orderBy('r.dateReception', 'DESC')
            ->addOrderBy('r.id', 'DESC');

        if ($currentReception instanceof FishReception && $currentReception->getId() !== null) {
            $builder
                ->andWhere('(r.quantiteReceptionnee > r.quantiteUtiliseeProduction OR r.id = :currentReceptionId)')
                ->setParameter('currentReceptionId', $currentReception->getId());
        } else {
            $builder->andWhere('r.quantiteReceptionnee > r.quantiteUtiliseeProduction');
        }

        return $builder->getQuery()->getResult();
    }

    /** @return list<FishReception> */
    public function findActivityBetween(\DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $fromDay = $from->setTime(0, 0);
        $toDay = $to->setTime(0, 0);
        $toEnd = $to->setTime(23, 59, 59);

        return $this->createQueryBuilder('r')
            ->leftJoin('r.createdBy', 'creator')
            ->leftJoin('r.updatedBy', 'updater')
            ->leftJoin('r.receivedBy', 'receiver')
            ->leftJoin('r.treatmentStartedBy', 'treatmentStarter')
            ->leftJoin('r.storedBy', 'storageUser')
            ->leftJoin('r.remiseEnChambreBy', 'returnStorageUser')
            ->leftJoin('r.expeditedBy', 'shippingUser')
            ->leftJoin('r.closedBy', 'closingUser')
            ->leftJoin('r.blockedBy', 'blockingUser')
            ->addSelect('creator', 'updater', 'receiver', 'treatmentStarter', 'storageUser', 'returnStorageUser', 'shippingUser', 'closingUser', 'blockingUser')
            ->andWhere('r.isDeleted = false')
            ->andWhere('(
                r.createdAt BETWEEN :fromDateTime AND :toDateTime
                OR r.updatedAt BETWEEN :fromDateTime AND :toDateTime
                OR r.dateReception BETWEEN :fromDay AND :toDay
                OR r.receivedAt BETWEEN :fromDateTime AND :toDateTime
                OR r.treatmentStartedAt BETWEEN :fromDateTime AND :toDateTime
                OR r.dateDebutTraitement BETWEEN :fromDay AND :toDay
                OR r.dateEntreeTunnel BETWEEN :fromDay AND :toDay
                OR r.dateSortieTunnel BETWEEN :fromDay AND :toDay
                OR r.storedAt BETWEEN :fromDateTime AND :toDateTime
                OR r.dateEntreeStockage BETWEEN :fromDay AND :toDay
                OR r.dateConditionnement BETWEEN :fromDay AND :toDay
                OR r.remiseEnChambreAt BETWEEN :fromDateTime AND :toDateTime
                OR r.dateRemiseEnChambre BETWEEN :fromDay AND :toDay
                OR r.expeditedAt BETWEEN :fromDateTime AND :toDateTime
                OR r.expeditionDateDepart BETWEEN :fromDay AND :toDay
                OR r.closedAt BETWEEN :fromDateTime AND :toDateTime
                OR r.blockedAt BETWEEN :fromDateTime AND :toDateTime
            )')
            ->setParameter('fromDateTime', $from)
            ->setParameter('toDateTime', $toEnd)
            ->setParameter('fromDay', $fromDay)
            ->setParameter('toDay', $toDay)
            ->orderBy('r.dateReception', 'DESC')
            ->addOrderBy('r.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /** @return list<string> */
    public function distinctValues(string $field): array
    {
        if (!in_array($field, [
            'fournisseur',
            'provenance',
            'especePoisson',
            'presentationProduit',
            'etatProduit',
            'categorieFraicheur',
            'produitConditionne',
            'chambreFroide',
            'destinationFinaleClient',
        ], true)) {
            throw new \InvalidArgumentException('Champ de filtre reception invalide.');
        }

        $rows = $this->createQueryBuilder('r')
            ->select(sprintf('DISTINCT r.%s AS value', $field))
            ->andWhere('r.isDeleted = false')
            ->andWhere(sprintf('r.%s IS NOT NULL', $field))
            ->andWhere(sprintf("r.%s <> ''", $field))
            ->orderBy(sprintf('r.%s', $field), 'ASC')
            ->getQuery()
            ->getArrayResult();

        return array_values(array_filter(array_map(
            static fn (mixed $value): string => trim((string) $value),
            array_column($rows, 'value'),
        )));
    }

    /** @param array<string, mixed> $filters */
    public function dashboardStats(array $filters = []): array
    {
        $items = $this->buildFilteredQuery($filters)
            ->setMaxResults(1000)
            ->getQuery()
            ->getResult();

        $stage = (string) ($filters['stage'] ?? 'reception');
        $received = array_sum(array_map(static fn (FishReception $item): float => match ($stage) {
            'traitement' => $item->getQuantiteStockInitialEntreeValue(),
            'congelation' => $item->getQuantiteTotalePrepareeValue(),
            'stockage' => $item->getQuantiteCongeleeValue(),
            'emballage' => $item->getQuantiteStockeeValue(),
            'remise_chambre' => $item->getQuantiteConditionneeValue(),
            'expedition' => $item->getQuantiteRemiseEnChambreValue(),
            default => $item->getQuantiteReceptionneeValue(),
        }, $items));
        $used = array_sum(array_map(static fn (FishReception $item): float => $item->getWorkflowMovedForStage($stage), $items));
        $stored = array_sum(array_map(static fn (FishReception $item): float => (float) $item->getQuantiteStockee(), $items));
        $blocked = count(array_filter($items, static fn (FishReception $item): bool => $item->getStatut() === FishReception::STATUS_BLOCKED));
        $available = array_sum(array_map(static fn (FishReception $item): float => $item->getWorkflowAvailableForStage($stage), $items));

        return [
            'count' => count($items),
            'received' => $received,
            'used' => $used,
            'available' => $available,
            'stored' => $stored,
            'blocked' => $blocked,
            'usage_rate' => $received > 0 ? ($used / $received) * 100 : 0.0,
        ];
    }

    /** @return list<array{location: string, quantity: string}> */
    public function currentStockByStorageLocation(): array
    {
        return $this->createQueryBuilder('r')
            ->select('r.chambreRemiseEnChambre AS location')
            ->addSelect('SUM(r.quantiteRemiseEnChambre - r.quantiteTotaleExpediee) AS quantity')
            ->andWhere('r.isDeleted = false')
            ->andWhere('r.chambreRemiseEnChambre IS NOT NULL')
            ->andWhere("r.chambreRemiseEnChambre <> ''")
            ->andWhere('r.quantiteRemiseEnChambre > r.quantiteTotaleExpediee')
            ->groupBy('r.chambreRemiseEnChambre')
            ->orderBy('quantity', 'DESC')
            ->getQuery()
            ->getArrayResult();
    }

    /** @return list<array{location: string, quantity: string}> */
    public function currentCrystallizationStockByStorageLocation(): array
    {
        return $this->createQueryBuilder('r')
            ->select('r.chambreFroide AS location')
            ->addSelect('SUM(r.quantiteStockee - r.quantiteConditionnee) AS quantity')
            ->andWhere('r.isDeleted = false')
            ->andWhere('r.chambreFroide IS NOT NULL')
            ->andWhere("r.chambreFroide <> ''")
            ->andWhere('r.quantiteStockee > r.quantiteConditionnee')
            ->groupBy('r.chambreFroide')
            ->orderBy('quantity', 'DESC')
            ->getQuery()
            ->getArrayResult();
    }

    /** @return list<array{location: string, quantity: string}> */
    public function currentLoadByTunnel(): array
    {
        return $this->createQueryBuilder('r')
            ->select('r.tunnel AS location')
            ->addSelect('SUM(r.quantiteCongelee - r.quantiteStockee) AS quantity')
            ->andWhere('r.isDeleted = false')
            ->andWhere('r.tunnel IS NOT NULL')
            ->andWhere("r.tunnel <> ''")
            ->andWhere('r.quantiteCongelee > r.quantiteStockee')
            ->groupBy('r.tunnel')
            ->orderBy('quantity', 'DESC')
            ->getQuery()
            ->getArrayResult();
    }

    /** @param array<string, mixed> $filters */
    private function buildFilteredQuery(array $filters): QueryBuilder
    {
        $builder = $this->createQueryBuilder('r')
            ->leftJoin('r.createdBy', 'creator')
            ->leftJoin('r.updatedBy', 'updater')
            ->addSelect('creator', 'updater')
            ->andWhere('r.isDeleted = false');

        $query = mb_strtolower(trim((string) ($filters['q'] ?? '')));
        if ($query !== '') {
            $builder
                ->andWhere('LOWER(r.numeroReception) LIKE :query
                    OR LOWER(r.numeroLot) LIKE :query
                    OR LOWER(r.fournisseur) LIKE :query
                    OR LOWER(COALESCE(r.provenance, \'\')) LIKE :query
                    OR LOWER(r.especePoisson) LIKE :query
                    OR LOWER(COALESCE(r.numeroBonLivraison, \'\')) LIKE :query
                    OR LOWER(COALESCE(r.chambreFroide, \'\')) LIKE :query
                    OR LOWER(COALESCE(r.destinationFinaleClient, \'\')) LIKE :query')
                ->setParameter('query', '%'.$query.'%');
        }

        if (!empty($filters['dateFrom'])) {
            $builder
                ->andWhere('r.dateReception >= :dateFrom')
                ->setParameter('dateFrom', new \DateTimeImmutable((string) $filters['dateFrom']));
        }

        if (!empty($filters['dateTo'])) {
            $builder
                ->andWhere('r.dateReception <= :dateTo')
                ->setParameter('dateTo', new \DateTimeImmutable((string) $filters['dateTo']));
        }

        foreach (['statut', 'fournisseur', 'especePoisson', 'chambreFroide'] as $field) {
            if (!empty($filters[$field])) {
                $builder
                    ->andWhere(sprintf('r.%s = :%s', $field, $field))
                    ->setParameter($field, (string) $filters[$field]);
            }
        }

        match ((string) ($filters['usage'] ?? '')) {
            'available' => $builder->andWhere($this->stageAvailableExpression((string) ($filters['stage'] ?? 'reception')).' > 0'),
            'used' => $builder->andWhere($this->stageMovedExpression((string) ($filters['stage'] ?? 'reception')).' > 0'),
            'empty' => $builder->andWhere($this->stageAvailableExpression((string) ($filters['stage'] ?? 'reception')).' <= 0'),
            default => null,
        };

        match ((string) ($filters['stage'] ?? '')) {
            'traitement' => $builder->andWhere('(r.quantiteReceptionnee > r.quantiteTotalePreparee OR r.quantiteTotalePreparee > 0)'),
            'congelation' => $builder->andWhere('r.quantiteTotalePreparee > 0'),
            'stockage' => $builder->andWhere('r.quantiteCongelee > 0'),
            'emballage' => $builder->andWhere('r.quantiteStockee > 0'),
            'remise_chambre' => $builder->andWhere('r.quantiteConditionnee > 0'),
            'expedition' => $builder->andWhere('r.quantiteRemiseEnChambre > 0'),
            default => null,
        };

        $sortMap = [
            'date' => 'r.dateReception',
            'reception' => 'r.numeroReception',
            'lot' => 'r.numeroLot',
            'fournisseur' => 'r.fournisseur',
            'available' => 'availableWeight',
            'received' => 'r.quantiteReceptionnee',
        ];
        if (($filters['sort'] ?? '') === 'available') {
            $builder->addSelect($this->stageAvailableExpression((string) ($filters['stage'] ?? 'reception')).' AS HIDDEN availableWeight');
        }

        $sort = $sortMap[(string) ($filters['sort'] ?? 'date')] ?? 'r.dateReception';
        $direction = strtolower((string) ($filters['direction'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC';

        return $builder
            ->orderBy($sort, $direction)
            ->addOrderBy('r.id', 'DESC');
    }

    private function stageAvailableExpression(string $stage): string
    {
        return match ($stage) {
            'congelation' => 'r.quantiteTotalePreparee - r.quantiteCongelee',
            'stockage' => 'r.quantiteCongelee - r.quantiteStockee',
            'emballage' => 'r.quantiteStockee - r.quantiteConditionnee',
            'remise_chambre' => 'r.quantiteConditionnee - r.quantiteRemiseEnChambre',
            'expedition' => 'r.quantiteRemiseEnChambre - r.quantiteTotaleExpediee',
            default => 'r.quantiteReceptionnee - r.quantiteTotalePreparee',
        };
    }

    private function stageMovedExpression(string $stage): string
    {
        return match ($stage) {
            'congelation' => 'r.quantiteCongelee',
            'stockage' => 'r.quantiteStockee',
            'emballage' => 'r.quantiteConditionnee',
            'remise_chambre' => 'r.quantiteRemiseEnChambre',
            'expedition' => 'r.quantiteTotaleExpediee',
            default => 'r.quantiteTotalePreparee',
        };
    }
}
