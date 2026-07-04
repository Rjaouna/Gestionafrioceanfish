<?php

namespace App\Repository;

use App\Entity\CoutRevient;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<CoutRevient> */
class CoutRevientRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CoutRevient::class);
    }

    /**
     * @param array<string, mixed> $filters
     *
     * @return list<CoutRevient>
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
            ->select('COUNT(DISTINCT c.id)')
            ->resetDQLPart('orderBy')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @param array<string, mixed> $filters
     *
     * @return list<CoutRevient>
     */
    public function findForExport(array $filters = []): array
    {
        return $this->buildFilteredQuery($filters)
            ->setMaxResults(1000)
            ->getQuery()
            ->getResult();
    }

    /** @return list<string> */
    public function distinctValues(string $field): array
    {
        if (!in_array($field, ['produit', 'client', 'responsableProduction', 'especePoisson'], true)) {
            throw new \InvalidArgumentException('Champ de filtre cout de revient invalide.');
        }

        $rows = $this->createQueryBuilder('c')
            ->select(sprintf('DISTINCT c.%s AS value', $field))
            ->andWhere('c.isDeleted = false')
            ->andWhere(sprintf('c.%s IS NOT NULL', $field))
            ->andWhere(sprintf("c.%s <> ''", $field))
            ->orderBy(sprintf('c.%s', $field), 'ASC')
            ->getQuery()
            ->getArrayResult();

        return array_values(array_filter(array_map(
            static fn (mixed $value): string => trim((string) $value),
            array_column($rows, 'value'),
        )));
    }

    public function countByLotPrefix(string $prefix): int
    {
        return (int) $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->andWhere('c.numeroLot LIKE :prefix')
            ->setParameter('prefix', $prefix.'%')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /** @param array<string, mixed> $filters */
    public function getDashboardStats(array $filters = []): array
    {
        $items = $this->findForExport($filters);
        $count = count($items);
        $poidsBrut = $this->sum($items, 'poidsBrutRecu');
        $poidsFini = $this->sum($items, 'poidsProduitFini');
        $coutTotal = $this->sum($items, 'coutTotalProduction');
        $margeTotale = $this->sum($items, 'margeTotale');
        $rentables = count(array_filter($items, static fn (CoutRevient $item): bool => $item->hasPrixVente() && (float) $item->getMargeKg() > 0));
        $nonRentables = count(array_filter($items, static fn (CoutRevient $item): bool => $item->hasPrixVente() && (float) $item->getMargeKg() < 0));

        return [
            'lots' => $count,
            'poids_brut_total' => $poidsBrut,
            'poids_fini_total' => $poidsFini,
            'rendement_moyen' => $poidsBrut > 0 ? ($poidsFini / $poidsBrut) * 100 : 0.0,
            'cout_total' => $coutTotal,
            'cout_moyen_kg' => $poidsFini > 0 ? $coutTotal / $poidsFini : 0.0,
            'marge_totale' => $margeTotale,
            'taux_marge_moyen' => $coutTotal > 0 ? ($margeTotale / $coutTotal) * 100 : 0.0,
            'lots_rentables' => $rentables,
            'lots_non_rentables' => $nonRentables,
        ];
    }

    /** @param array<string, mixed> $filters */
    public function getCostBreakdown(array $filters = []): array
    {
        $items = $this->findForExport($filters);

        return [
            ['label' => 'Matiere premiere', 'value' => $this->sum($items, 'coutMatierePremiere')],
            ['label' => 'Main d oeuvre', 'value' => $this->sum($items, 'coutMainOeuvre')],
            ['label' => 'Emballage', 'value' => $this->sum($items, 'coutEmballageTotal')],
            ['label' => 'Charges diverses', 'value' => $this->sum($items, 'coutChargesTotal')],
        ];
    }

    /** @param array<string, mixed> $filters */
    public function getMarginByLot(array $filters = [], int $limit = 12): array
    {
        return array_map(static fn (CoutRevient $item): array => [
            'label' => (string) $item->getNumeroLot(),
            'value' => (float) $item->getMargeTotale(),
        ], array_slice($this->findForExport($filters), 0, $limit));
    }

    /** @param array<string, mixed> $filters */
    public function getRendementByLot(array $filters = [], int $limit = 12): array
    {
        return array_map(static fn (CoutRevient $item): array => [
            'label' => (string) $item->getNumeroLot(),
            'value' => (float) $item->getRendementPourcentage(),
        ], array_slice($this->findForExport($filters), 0, $limit));
    }

    /** @param array<string, mixed> $filters */
    public function getCoutKgEvolution(array $filters = [], int $limit = 12): array
    {
        $items = array_reverse(array_slice($this->findForExport($filters), 0, $limit));

        return array_map(static fn (CoutRevient $item): array => [
            'label' => $item->getDateProduction()?->format('d/m') ?? (string) $item->getNumeroLot(),
            'value' => (float) $item->getCoutRevientKg(),
        ], $items);
    }

    /** @param array<string, mixed> $filters */
    public function getRentabilityStats(array $filters = []): array
    {
        $items = $this->findForExport($filters);
        $rentables = count(array_filter($items, static fn (CoutRevient $item): bool => $item->hasPrixVente() && (float) $item->getMargeKg() > 0));
        $nonRentables = count(array_filter($items, static fn (CoutRevient $item): bool => $item->hasPrixVente() && (float) $item->getMargeKg() < 0));
        $neutres = count(array_filter($items, static fn (CoutRevient $item): bool => $item->hasPrixVente() && abs((float) $item->getMargeKg()) < 0.01));
        $sansPrix = count($items) - $rentables - $nonRentables - $neutres;

        return [
            ['label' => 'Rentables', 'value' => $rentables],
            ['label' => 'Non rentables', 'value' => $nonRentables],
            ['label' => 'Marge nulle', 'value' => $neutres],
            ['label' => 'Sans prix vente', 'value' => max(0, $sansPrix)],
        ];
    }

    /** @param array<string, mixed> $filters */
    private function buildFilteredQuery(array $filters): QueryBuilder
    {
        $builder = $this->createQueryBuilder('c')
            ->leftJoin('c.createdBy', 'creator')
            ->leftJoin('c.updatedBy', 'updater')
            ->leftJoin('c.validatedBy', 'validator')
            ->addSelect('creator', 'updater', 'validator')
            ->andWhere('c.isDeleted = false');

        $query = mb_strtolower(trim((string) ($filters['q'] ?? '')));
        if ($query !== '') {
            $builder
                ->andWhere('LOWER(c.numeroLot) LIKE :query
                    OR LOWER(c.produit) LIKE :query
                    OR LOWER(COALESCE(c.especePoisson, \'\')) LIKE :query
                    OR LOWER(COALESCE(c.client, \'\')) LIKE :query
                    OR LOWER(COALESCE(c.responsableProduction, \'\')) LIKE :query')
                ->setParameter('query', '%'.$query.'%');
        }

        if (!empty($filters['dateFrom'])) {
            $builder
                ->andWhere('c.dateProduction >= :dateFrom')
                ->setParameter('dateFrom', new \DateTimeImmutable((string) $filters['dateFrom']));
        }

        if (!empty($filters['dateTo'])) {
            $builder
                ->andWhere('c.dateProduction <= :dateTo')
                ->setParameter('dateTo', new \DateTimeImmutable((string) $filters['dateTo']));
        }

        if (!empty($filters['produit'])) {
            $builder
                ->andWhere('c.produit = :produit')
                ->setParameter('produit', (string) $filters['produit']);
        }

        if (!empty($filters['client'])) {
            $builder
                ->andWhere('c.client = :client')
                ->setParameter('client', (string) $filters['client']);
        }

        if (!empty($filters['statut']) && isset(CoutRevient::STATUS_LABELS[(string) $filters['statut']])) {
            $builder
                ->andWhere('c.statut = :statut')
                ->setParameter('statut', (string) $filters['statut']);
        }

        match ((string) ($filters['rentabilite'] ?? '')) {
            'rentable' => $builder->andWhere('c.prixVenteKg IS NOT NULL')->andWhere('c.prixVenteKg > 0')->andWhere('c.margeKg > 0'),
            'non_rentable' => $builder->andWhere('c.prixVenteKg IS NOT NULL')->andWhere('c.prixVenteKg > 0')->andWhere('c.margeKg < 0'),
            'marge_nulle' => $builder->andWhere('c.prixVenteKg IS NOT NULL')->andWhere('c.prixVenteKg > 0')->andWhere('c.margeKg = 0'),
            'sans_prix' => $builder->andWhere('c.prixVenteKg IS NULL OR c.prixVenteKg <= 0'),
            default => null,
        };

        $sortMap = [
            'date' => 'c.dateProduction',
            'marge' => 'c.margeTotale',
            'rendement' => 'c.rendementPourcentage',
            'coutKg' => 'c.coutRevientKg',
            'lot' => 'c.numeroLot',
        ];
        $sort = $sortMap[(string) ($filters['sort'] ?? 'date')] ?? 'c.dateProduction';
        $direction = strtolower((string) ($filters['direction'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC';

        return $builder
            ->orderBy($sort, $direction)
            ->addOrderBy('c.id', 'DESC');
    }

    /** @param list<CoutRevient> $items */
    private function sum(array $items, string $property): float
    {
        return array_sum(array_map(static fn (CoutRevient $item): float => $item->floatValue($property), $items));
    }
}
