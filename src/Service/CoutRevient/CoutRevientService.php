<?php

namespace App\Service\CoutRevient;

use App\Entity\CoutRevient;
use App\Entity\CoutRevientChargeConfig;
use App\Entity\CoutRevientChargeLine;
use App\Entity\User;
use App\Repository\CoutRevientChargeConfigRepository;
use App\Repository\CoutRevientRepository;
use App\Service\Trash\TrashService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final readonly class CoutRevientService
{
    public function __construct(
        private CoutRevientRepository $repository,
        private EntityManagerInterface $entityManager,
        private CoutRevientPermissionService $permission,
        private CoutRevientCalculatorService $calculator,
        private CoutRevientChargeConfigRepository $chargeConfigRepository,
        private TrashService $trashService,
    ) {
    }

    /**
     * @param array<string, mixed> $filters
     *
     * @return array{items: list<CoutRevient>, total: int, page: int, pages: int, perPage: int, filters: array<string, mixed>}
     */
    public function search(User $actor, array $filters = [], int $page = 1, int $perPage = 15): array
    {
        if (!$this->permission->canAccess($actor)) {
            throw new AccessDeniedException();
        }

        $filters = $this->normalizeFilters($filters);
        $page = max(1, $page);
        $perPage = max(1, min(60, $perPage));
        $total = $this->repository->countWithFilters($filters);
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min($page, $pages);

        return [
            'items' => $this->repository->searchWithFilters($filters, $page, $perPage),
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
            'perPage' => $perPage,
            'filters' => $filters,
        ];
    }

    /** @return array<string, mixed> */
    public function filterChoices(User $actor): array
    {
        if (!$this->permission->canAccess($actor)) {
            throw new AccessDeniedException();
        }

        return [
            'produits' => $this->repository->distinctValues('produit'),
            'clients' => $this->repository->distinctValues('client'),
            'statuts' => CoutRevient::STATUS_LABELS,
            'rentabilites' => [
                'rentable' => 'Rentable',
                'non_rentable' => 'Non rentable',
                'marge_nulle' => 'Marge nulle',
                'sans_prix' => 'Sans prix vente',
            ],
            'sorts' => [
                'date' => 'Date',
                'marge' => 'Marge',
                'rendement' => 'Rendement',
                'coutKg' => 'Cout / kg',
                'lot' => 'Lot',
            ],
        ];
    }

    /** @param array<int|string, mixed> $chargeRows */
    public function create(CoutRevient $coutRevient, User $actor, bool $validate = false, array $chargeRows = []): CoutRevient
    {
        if (!$this->permission->canCreate($actor)) {
            throw new AccessDeniedException();
        }

        $this->prepare($coutRevient);
        $this->syncChargeLines($coutRevient, $chargeRows, $actor);
        if ($validate) {
            $this->applyValidation($coutRevient, $actor);
        } else {
            $coutRevient->setStatut(CoutRevient::STATUS_DRAFT);
            $this->calculator->calculate($coutRevient);
        }

        $coutRevient->setCreatedBy($actor);
        $this->entityManager->persist($coutRevient);
        $this->entityManager->flush();

        return $coutRevient;
    }

    /** @param array<int|string, mixed> $chargeRows */
    public function update(CoutRevient $coutRevient, User $actor, bool $validate = false, array $chargeRows = []): CoutRevient
    {
        if (!$this->permission->canEdit($actor, $coutRevient)) {
            throw new AccessDeniedException();
        }

        $this->prepare($coutRevient);
        $this->syncChargeLines($coutRevient, $chargeRows, $actor);
        if ($validate) {
            $this->applyValidation($coutRevient, $actor);
        } else {
            $this->calculator->calculate($coutRevient);
        }

        $this->entityManager->flush();

        return $coutRevient;
    }

    public function validate(CoutRevient $coutRevient, User $actor): CoutRevient
    {
        if (!$this->permission->canValidate($actor, $coutRevient)) {
            throw new AccessDeniedException();
        }

        $this->applyValidation($coutRevient, $actor);
        $this->entityManager->flush();

        return $coutRevient;
    }

    public function duplicate(CoutRevient $source, User $actor): CoutRevient
    {
        if (!$this->permission->canDuplicate($actor, $source)) {
            throw new AccessDeniedException();
        }

        $duplicate = (new CoutRevient())
            ->setDateProduction(new \DateTimeImmutable('today'))
            ->setNumeroLot($this->nextLotNumber())
            ->setProduit((string) $source->getProduit())
            ->setEspecePoisson($source->getEspecePoisson())
            ->setClient($source->getClient())
            ->setResponsableProduction($source->getResponsableProduction())
            ->setObservation($source->getObservation())
            ->setPoidsBrutRecu($source->getPoidsBrutRecu())
            ->setPoidsMisEnProduction($source->getPoidsMisEnProduction())
            ->setPrixAchatKg($source->getPrixAchatKg())
            ->setFraisTransportAchat($source->getFraisTransportAchat())
            ->setAutresFraisAchat($source->getAutresFraisAchat())
            ->setPoidsProduitFini($source->getPoidsProduitFini())
            ->setPoidsDechets($source->getPoidsDechets())
            ->setPoidsPerte($source->getPoidsPerte())
            ->setModeCalculMainOeuvre($source->getModeCalculMainOeuvre())
            ->setNombreOperatrices($source->getNombreOperatrices())
            ->setNombreHeures($source->getNombreHeures())
            ->setCoutHoraireMoyen($source->getCoutHoraireMoyen())
            ->setPrixTacheKg($source->getPrixTacheKg())
            ->setKgTraitesMainOeuvre($source->getKgTraitesMainOeuvre())
            ->setCoutMainOeuvreDirect($source->getCoutMainOeuvreDirect())
            ->setNombreCartons($source->getNombreCartons())
            ->setPrixCarton($source->getPrixCarton())
            ->setNombreSachets($source->getNombreSachets())
            ->setPrixSachet($source->getPrixSachet())
            ->setCoutEtiquettes($source->getCoutEtiquettes())
            ->setCoutFilmPlastique($source->getCoutFilmPlastique())
            ->setAutresCoutEmballage($source->getAutresCoutEmballage())
            ->setCoutElectricite($source->getCoutElectricite())
            ->setCoutEau($source->getCoutEau())
            ->setCoutGlace($source->getCoutGlace())
            ->setCoutNettoyage($source->getCoutNettoyage())
            ->setCoutMaintenance($source->getCoutMaintenance())
            ->setCoutTransportLivraison($source->getCoutTransportLivraison())
            ->setAutresCharges($source->getAutresCharges())
            ->setPrixVenteKg($source->getPrixVenteKg())
            ->setCreatedBy($actor);

        foreach ($source->getChargeLines() as $sourceLine) {
            $duplicateLine = (new CoutRevientChargeLine())
                ->setChargeConfig($sourceLine->getChargeConfig())
                ->setName($sourceLine->getName())
                ->setCategory($sourceLine->getCategory())
                ->setCalculationUnit($sourceLine->getCalculationUnit())
                ->setUnitCost($sourceLine->getUnitCost())
                ->setQuantity($sourceLine->getQuantity())
                ->setNote($sourceLine->getNote())
                ->setSortOrder($sourceLine->getSortOrder())
                ->setCreatedBy($actor)
                ->recalculate();
            $duplicate->addChargeLine($duplicateLine);
        }

        $this->calculator->calculate($duplicate);
        $this->entityManager->persist($duplicate);
        $this->entityManager->flush();

        return $duplicate;
    }

    public function delete(CoutRevient $coutRevient, User $actor): bool
    {
        if (!$this->permission->canDelete($actor, $coutRevient)) {
            throw new AccessDeniedException();
        }

        if (!$this->permission->isSuperAdmin($actor)) {
            $this->trashService->moveToTrash($coutRevient, $actor);

            return true;
        }

        $this->entityManager->remove($coutRevient);
        $this->entityManager->flush();

        return false;
    }

    /** @param array<string, mixed> $filters */
    public function exportItems(User $actor, array $filters = []): array
    {
        if (!$this->permission->canExport($actor)) {
            throw new AccessDeniedException();
        }

        return $this->repository->findForExport($this->normalizeFilters($filters));
    }

    /** @param array<string, mixed> $filters */
    public function normalizeFilters(array $filters): array
    {
        $normalized = [
            'q' => trim((string) ($filters['q'] ?? '')),
            'dateFrom' => trim((string) ($filters['dateFrom'] ?? '')),
            'dateTo' => trim((string) ($filters['dateTo'] ?? '')),
            'produit' => trim((string) ($filters['produit'] ?? '')),
            'client' => trim((string) ($filters['client'] ?? '')),
            'statut' => trim((string) ($filters['statut'] ?? '')),
            'rentabilite' => trim((string) ($filters['rentabilite'] ?? '')),
            'sort' => trim((string) ($filters['sort'] ?? 'date')),
            'direction' => trim((string) ($filters['direction'] ?? 'desc')),
        ];

        foreach (['dateFrom', 'dateTo'] as $dateKey) {
            if ($normalized[$dateKey] !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $normalized[$dateKey])) {
                $normalized[$dateKey] = '';
            }
        }

        if ($normalized['statut'] !== '' && !isset(CoutRevient::STATUS_LABELS[$normalized['statut']])) {
            $normalized['statut'] = '';
        }

        if (!in_array($normalized['rentabilite'], ['', 'rentable', 'non_rentable', 'marge_nulle', 'sans_prix'], true)) {
            $normalized['rentabilite'] = '';
        }

        if (!in_array($normalized['sort'], ['date', 'marge', 'rendement', 'coutKg', 'lot'], true)) {
            $normalized['sort'] = 'date';
        }

        $normalized['direction'] = strtolower($normalized['direction']) === 'asc' ? 'asc' : 'desc';

        return $normalized;
    }

    private function prepare(CoutRevient $coutRevient): void
    {
        if ($coutRevient->getDateProduction() === null) {
            $coutRevient->setDateProduction(new \DateTimeImmutable('today'));
        }

        if ($coutRevient->getNumeroLot() === null || $coutRevient->getNumeroLot() === '') {
            $coutRevient->setNumeroLot($this->nextLotNumber());
        }
    }

    /** @param array<int|string, mixed> $chargeRows */
    private function syncChargeLines(CoutRevient $coutRevient, array $chargeRows, User $actor): void
    {
        foreach ($coutRevient->getChargeLines()->toArray() as $line) {
            $coutRevient->removeChargeLine($line);
            if ($line->getId() !== null) {
                $this->entityManager->remove($line);
            }
        }

        $sortOrder = 0;
        foreach ($chargeRows as $row) {
            if (!is_array($row)) {
                continue;
            }

            if (!empty($row['remove'])) {
                continue;
            }

            $config = null;
            $configId = (int) ($row['chargeConfig'] ?? 0);
            if ($configId > 0) {
                $config = $this->chargeConfigRepository->find($configId);
            }

            $line = new CoutRevientChargeLine();
            if ($config instanceof CoutRevientChargeConfig) {
                $line->applyConfig($config);
            }

            $name = trim((string) ($row['name'] ?? ''));
            $category = trim((string) ($row['category'] ?? ''));
            $calculationUnit = trim((string) ($row['calculationUnit'] ?? ''));

            if ($name !== '') {
                $line->setName($name);
            }

            if ($category !== '') {
                $line->setCategory($category);
            }

            if ($calculationUnit !== '') {
                $line->setCalculationUnit($calculationUnit);
            }

            $line
                ->setUnitCost($row['unitCost'] ?? $line->getUnitCost())
                ->setQuantity($row['quantity'] ?? 0)
                ->setNote((string) ($row['note'] ?? ''))
                ->setSortOrder(++$sortOrder)
                ->setCreatedBy($actor)
                ->recalculate();

            if ($line->getName() === '' || (float) $line->getQuantity() <= 0) {
                continue;
            }

            $coutRevient->addChargeLine($line);
        }
    }

    private function applyValidation(CoutRevient $coutRevient, User $actor): void
    {
        $this->calculator->assertValidatable($coutRevient);
        $coutRevient
            ->setStatut(CoutRevient::STATUS_VALIDATED)
            ->setValidatedAt(new \DateTimeImmutable())
            ->setValidatedBy($actor);
    }

    private function nextLotNumber(): string
    {
        $prefix = sprintf('CR-%s-', (new \DateTimeImmutable())->format('Y'));
        $sequence = $this->repository->countByLotPrefix($prefix) + 1;

        do {
            $number = sprintf('%s%04d', $prefix, $sequence++);
        } while ($this->repository->findOneBy(['numeroLot' => $number]) instanceof CoutRevient);

        return $number;
    }
}
