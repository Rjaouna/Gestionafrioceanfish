<?php

namespace App\Service\FishReception;

use App\Entity\CoutRevient;
use App\Entity\FishReception;
use App\Entity\User;
use App\Repository\FishReceptionRepository;
use App\Service\FactoryUnitService;
use App\Service\Trash\TrashService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final readonly class FishReceptionService
{
    private const WORKFLOW_STAGES = [
        'reception' => 'Reception',
        'traitement' => 'Traitement / Production',
        'emballage' => 'Conditionnement / Emballage',
        'congelation' => 'Congelation',
        'stockage' => 'Stockage',
        'expedition' => 'Expedition',
    ];

    public function __construct(
        private FishReceptionRepository $repository,
        private EntityManagerInterface $entityManager,
        private FishReceptionPermissionService $permission,
        private FactoryUnitService $factoryUnitService,
        private TrashService $trashService,
    ) {
    }

    /**
     * @param array<string, mixed> $filters
     *
     * @return array{items: list<FishReception>, total: int, page: int, pages: int, perPage: int, filters: array<string, mixed>}
     */
    public function search(User $actor, array $filters = [], int $page = 1, int $perPage = 15): array
    {
        $this->denyUnlessAccess($actor);
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
    public function dashboard(User $actor, array $filters = []): array
    {
        $this->denyUnlessAccess($actor);

        return $this->repository->dashboardStats($this->normalizeFilters($filters));
    }

    /** @return array<string, mixed> */
    public function filterChoices(User $actor): array
    {
        $this->denyUnlessAccess($actor);

        return [
            'statuts' => FishReception::STATUS_LABELS,
            'usages' => [
                'available' => 'Disponible etape',
                'used' => 'Deja transferee',
                'empty' => 'Vide',
            ],
            'fournisseurs' => $this->repository->distinctValues('fournisseur'),
            'especes' => $this->repository->distinctValues('especePoisson'),
            'chambres' => $this->repository->distinctValues('chambreFroide'),
            'sorts' => [
                'date' => 'Date',
                'reception' => 'Reception',
                'lot' => 'Lot',
                'fournisseur' => 'Fournisseur',
                'available' => 'Disponible',
                'received' => 'Quantite recue',
            ],
        ];
    }

    /** @return array<string, list<string>> */
    public function formChoiceLists(User $actor): array
    {
        $this->denyUnlessAccess($actor);

        $fields = [
            'fournisseur',
            'provenance',
            'especePoisson',
            'presentationProduit',
            'etatProduit',
            'categorieFraicheur',
            'produitConditionne',
            'destinationFinaleClient',
        ];
        $choices = [];
        foreach ($fields as $field) {
            $choices[$field] = $this->repository->distinctValues($field);
        }

        return $choices;
    }

    /** @return array<string, string> */
    public function workflowStages(): array
    {
        return self::WORKFLOW_STAGES;
    }

    public function create(FishReception $reception, User $actor): FishReception
    {
        if (!$this->permission->canCreate($actor)) {
            throw new AccessDeniedException();
        }

        $this->prepare($reception);
        $this->autoRefreshStatus($reception);
        $reception->setCreatedBy($actor);

        $this->entityManager->persist($reception);
        $this->entityManager->flush();

        return $reception;
    }

    public function update(FishReception $reception, User $actor): FishReception
    {
        if (!$this->permission->canEdit($actor, $reception)) {
            throw new AccessDeniedException();
        }

        $this->prepare($reception);
        $this->assertQuantitiesCoherent($reception);
        $this->autoRefreshStatus($reception);
        $this->entityManager->flush();

        return $reception;
    }

    public function validateReception(FishReception $reception, User $actor): FishReception
    {
        $this->denyUnlessTransition($actor, $reception);
        $this->assertReceptionReady($reception);

        $reception
            ->setStatut(FishReception::STATUS_RECEIVED)
            ->setReceivedAt($reception->getReceivedAt() ?? new \DateTimeImmutable())
            ->setReceivedBy($actor);

        $this->entityManager->flush();

        return $reception;
    }

    public function startTreatment(FishReception $reception, User $actor): FishReception
    {
        return $this->launchTreatment($reception, $reception->getQuantiteDisponibleReceptionValue(), $actor);
    }

    public function launchTreatment(FishReception $reception, float $quantity, User $actor): FishReception
    {
        $this->denyUnlessTransition($actor, $reception);
        $this->assertReceptionReady($reception);
        $this->assertStageQuantity($reception, $quantity, $reception->getQuantiteDisponibleReceptionValue(), 'la reception');
        if ($reception->getStatut() === FishReception::STATUS_DRAFT) {
            $reception
                ->setReceivedAt($reception->getReceivedAt() ?? new \DateTimeImmutable())
                ->setReceivedBy($actor);
        }

        $now = new \DateTimeImmutable();
        $reception
            ->setQuantiteTotalePreparee($reception->getQuantiteTotalePrepareeValue() + $quantity)
            ->setStatut(FishReception::STATUS_PROCESSING)
            ->setTreatmentStartedAt($now)
            ->setTreatmentStartedBy($actor);

        if ($reception->getHeureDebutTraitement() === null) {
            $reception->setHeureDebutTraitement($now);
        }

        $this->assertQuantitiesCoherent($reception);
        $this->entityManager->flush();

        return $reception;
    }

    public function cancelTreatment(FishReception $reception, float $quantity, User $actor, ?string $reason = null): FishReception
    {
        $this->denyUnlessTransition($actor, $reception);
        $this->assertReceptionReady($reception);
        $this->assertStageQuantity($reception, $quantity, $reception->getQuantiteDisponibleTraitementValue(), 'le traitement non emballe');

        $reception->setQuantiteTotalePreparee(max(0.0, $reception->getQuantiteTotalePrepareeValue() - $quantity));
        $this->appendTreatmentCancelTrace($reception, $quantity, $actor, $reason);
        $this->autoRefreshStatus($reception);
        $this->assertQuantitiesCoherent($reception);
        $this->entityManager->flush();

        return $reception;
    }

    public function markStored(FishReception $reception, User $actor): FishReception
    {
        return $this->registerStorage($reception, $reception->getQuantiteDisponibleCongelationValue(), $actor);
    }

    public function registerPackaging(FishReception $reception, float $quantity, User $actor): FishReception
    {
        $this->denyUnlessTransition($actor, $reception);
        $this->assertReceptionReady($reception);
        $this->assertStageQuantity($reception, $quantity, $reception->getQuantiteDisponibleTraitementValue(), 'le traitement');
        if (!$reception->getProduitConditionne()) {
            throw new \DomainException('Indiquez le produit conditionne avant de valider l\'emballage.');
        }
        $now = new \DateTimeImmutable();

        $reception
            ->setQuantiteConditionnee($reception->getQuantiteConditionneeValue() + $quantity)
            ->setStatut(FishReception::STATUS_PACKAGED);

        if ($reception->getDateConditionnement() === null) {
            $reception->setDateConditionnement($now);
        }

        if ($reception->getHeureDebutConditionnement() === null) {
            $reception->setHeureDebutConditionnement($now);
        }

        $this->assertQuantitiesCoherent($reception);
        $this->entityManager->flush();

        return $reception;
    }

    public function registerFreezing(FishReception $reception, float $quantity, User $actor): FishReception
    {
        $this->denyUnlessTransition($actor, $reception);
        $this->assertReceptionReady($reception);
        $this->assertStageQuantity($reception, $quantity, $reception->getQuantiteDisponibleEmballageValue(), "l'emballage");
        if (!$reception->getTunnel()) {
            throw new \DomainException('Selectionnez le tunnel avant de valider la congelation.');
        }
        $this->factoryUnitService->assertTunnelCanReceive($actor, $reception->getTunnel(), $quantity);
        $now = new \DateTimeImmutable();

        $reception
            ->setQuantiteCongelee($reception->getQuantiteCongeleeValue() + $quantity)
            ->setStatut(FishReception::STATUS_FROZEN);

        if ($reception->getHeureEntreeTunnel() === null) {
            $reception->setHeureEntreeTunnel($now);
        }

        $this->assertQuantitiesCoherent($reception);
        $this->entityManager->flush();

        return $reception;
    }

    public function registerStorage(FishReception $reception, float $quantity, User $actor): FishReception
    {
        $this->denyUnlessTransition($actor, $reception);
        $this->assertReceptionReady($reception);
        $this->assertStageQuantity($reception, $quantity, $reception->getQuantiteDisponibleCongelationValue(), 'la congelation');
        if (!$reception->getChambreFroide()) {
            throw new \DomainException('Selectionnez la chambre froide ou la zone de stockage.');
        }
        $this->factoryUnitService->assertStorageCanReceive($actor, $reception->getChambreFroide(), $quantity);
        $now = new \DateTimeImmutable();

        $reception
            ->setQuantiteStockee($reception->getQuantiteStockeeValue() + $quantity)
            ->setStatut(FishReception::STATUS_STORED)
            ->setStoredAt($now)
            ->setStoredBy($actor);

        if ($reception->getDateEntreeStockage() === null) {
            $reception->setDateEntreeStockage($now);
        }

        if ($reception->getHeureEntreeStockage() === null) {
            $reception->setHeureEntreeStockage($now);
        }

        $this->assertQuantitiesCoherent($reception);
        $this->entityManager->flush();

        return $reception;
    }

    public function registerShipping(FishReception $reception, float $quantity, User $actor): FishReception
    {
        $this->denyUnlessTransition($actor, $reception);
        $this->assertReceptionReady($reception);
        $this->assertStageQuantity($reception, $quantity, $reception->getQuantiteDisponibleStockageValue(), 'le stockage');
        if (!$reception->getDestinationFinaleClient()) {
            throw new \DomainException('Indiquez la destination ou le client avant de valider l\'expedition.');
        }
        $now = new \DateTimeImmutable();
        if ($reception->getExpeditionDateDepart() === null) {
            $reception->setExpeditionDateDepart($now);
        }
        if ($reception->getExpeditionHeureDepart() === null) {
            $reception->setExpeditionHeureDepart($now);
        }
        if (!$reception->getExpeditionMatriculeVehicule()) {
            throw new \DomainException('Indiquez le matricule du camion avant de valider l\'expedition.');
        }
        if (!$reception->getExpeditionChauffeur()) {
            throw new \DomainException('Indiquez le nom du chauffeur avant de valider l\'expedition.');
        }
        if (!$reception->getExpeditionResponsableChargement()) {
            throw new \DomainException('Indiquez le responsable du chargement avant de valider l\'expedition.');
        }
        if ($reception->getDateEntreeStockage() instanceof \DateTimeImmutable && $reception->getExpeditionDateDepart()->format('Y-m-d') < $reception->getDateEntreeStockage()->format('Y-m-d')) {
            throw new \DomainException('La date d\'expedition ne peut pas etre avant la date d\'entree en stockage.');
        }

        $reception
            ->setQuantiteTotaleExpediee($reception->getQuantiteTotaleExpedieeValue() + $quantity)
            ->setStatut(FishReception::STATUS_SHIPPED)
            ->setExpeditedAt($now)
            ->setExpeditedBy($actor);

        $this->assertQuantitiesCoherent($reception);
        $this->entityManager->flush();

        return $reception;
    }

    public function close(FishReception $reception, User $actor): FishReception
    {
        $this->denyUnlessTransition($actor, $reception);
        if ($reception->getStatut() === FishReception::STATUS_BLOCKED) {
            throw new \DomainException('Une reception bloquee ne peut pas etre cloturee.');
        }

        $reception
            ->setStatut(FishReception::STATUS_CLOSED)
            ->setClosedAt(new \DateTimeImmutable())
            ->setClosedBy($actor);

        $this->entityManager->flush();

        return $reception;
    }

    public function block(FishReception $reception, User $actor, string $reason): FishReception
    {
        $this->denyUnlessTransition($actor, $reception);
        if ($reception->getStatut() === FishReception::STATUS_CLOSED) {
            throw new \DomainException('Une reception cloturee ne peut pas etre bloquee.');
        }

        $reason = trim($reason);
        if ($reason === '') {
            throw new \DomainException('Indiquez le motif du blocage.');
        }

        $reception
            ->setStatut(FishReception::STATUS_BLOCKED)
            ->setBlockedAt(new \DateTimeImmutable())
            ->setBlockedBy($actor)
            ->setBlockReason($reason);

        $this->entityManager->flush();

        return $reception;
    }

    public function delete(FishReception $reception, User $actor): bool
    {
        if (!$this->permission->canDelete($actor, $reception)) {
            throw new AccessDeniedException();
        }

        if ($reception->getQuantiteTotalePrepareeValue() > 0.001 || $reception->getQuantiteUtiliseeProductionValue() > 0.001 || $reception->getCoutRevients()->count() > 0) {
            throw new \DomainException('Impossible de supprimer une reception deja utilisee dans le workflow ou rattachee a un lot.');
        }

        if (!$this->permission->isSuperAdmin($actor)) {
            $this->trashService->moveToTrash($reception, $actor);

            return true;
        }

        $this->entityManager->remove($reception);
        $this->entityManager->flush();

        return false;
    }

    public function syncProductionAllocation(CoutRevient $lot, ?FishReception $previousReception, float $previousQuantity): void
    {
        $newReception = $lot->getReception();
        $newQuantity = (float) $lot->getPoidsMisEnProduction();

        if (!$previousReception instanceof FishReception && !$newReception instanceof FishReception) {
            return;
        }

        if ($previousReception instanceof FishReception && $newReception instanceof FishReception && $previousReception->getId() === $newReception->getId()) {
            $availableIncludingCurrent = $newReception->getQuantiteDisponibleProductionValue() + $previousQuantity;
            $this->assertReceptionCanProvide($newReception, $newQuantity, $availableIncludingCurrent);
            $newReception->setQuantiteUtiliseeProduction($newReception->getQuantiteUtiliseeProductionValue() - $previousQuantity + $newQuantity);

            return;
        }

        if ($previousReception instanceof FishReception) {
            $previousReception->setQuantiteUtiliseeProduction(max(0.0, $previousReception->getQuantiteUtiliseeProductionValue() - $previousQuantity));
        }

        if ($newReception instanceof FishReception) {
            $this->assertReceptionCanProvide($newReception, $newQuantity, $newReception->getQuantiteDisponibleProductionValue());
            $newReception->setQuantiteUtiliseeProduction($newReception->getQuantiteUtiliseeProductionValue() + $newQuantity);
        }
    }

    /** @return list<FishReception> */
    public function availableForProduction(User $actor, ?FishReception $currentReception = null): array
    {
        $this->denyUnlessAccess($actor);

        return $this->repository->availableForProduction($currentReception);
    }

    /** @param array<string, mixed> $filters */
    public function normalizeFilters(array $filters): array
    {
        $normalized = [
            'q' => trim((string) ($filters['q'] ?? '')),
            'dateFrom' => trim((string) ($filters['dateFrom'] ?? '')),
            'dateTo' => trim((string) ($filters['dateTo'] ?? '')),
            'statut' => trim((string) ($filters['statut'] ?? '')),
            'usage' => trim((string) ($filters['usage'] ?? '')),
            'fournisseur' => trim((string) ($filters['fournisseur'] ?? '')),
            'especePoisson' => trim((string) ($filters['especePoisson'] ?? '')),
            'chambreFroide' => trim((string) ($filters['chambreFroide'] ?? '')),
            'sort' => trim((string) ($filters['sort'] ?? 'date')),
            'direction' => trim((string) ($filters['direction'] ?? 'desc')),
            'stage' => trim((string) ($filters['stage'] ?? '')),
        ];

        foreach (['dateFrom', 'dateTo'] as $dateKey) {
            if ($normalized[$dateKey] !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $normalized[$dateKey])) {
                $normalized[$dateKey] = '';
            }
        }

        if ($normalized['statut'] !== '' && !isset(FishReception::STATUS_LABELS[$normalized['statut']])) {
            $normalized['statut'] = '';
        }

        if (!in_array($normalized['usage'], ['', 'available', 'used', 'empty'], true)) {
            $normalized['usage'] = '';
        }

        if (!in_array($normalized['sort'], ['date', 'reception', 'lot', 'fournisseur', 'available', 'received'], true)) {
            $normalized['sort'] = 'date';
        }

        if ($normalized['stage'] !== '' && !isset(self::WORKFLOW_STAGES[$normalized['stage']])) {
            $normalized['stage'] = '';
        }

        $normalized['direction'] = strtolower($normalized['direction']) === 'asc' ? 'asc' : 'desc';

        return $normalized;
    }

    private function prepare(FishReception $reception): void
    {
        if ($reception->getDateReception() === null) {
            $reception->setDateReception(new \DateTimeImmutable('today'));
        }

        if (!$reception->getNumeroReception()) {
            $reception->setNumeroReception($this->nextReceptionNumber());
        }

        if (!$reception->getNumeroLot()) {
            $reception->setNumeroLot($this->nextLotNumber());
        }
    }

    private function autoRefreshStatus(FishReception $reception): void
    {
        if ($reception->isLocked()) {
            return;
        }

        $status = match (true) {
            (float) $reception->getQuantiteTotaleExpediee() > 0 || $reception->getExpeditedAt() !== null => FishReception::STATUS_SHIPPED,
            (float) $reception->getQuantiteStockee() > 0 || $reception->getChambreFroide() !== null => FishReception::STATUS_STORED,
            (float) $reception->getQuantiteCongelee() > 0 || $reception->getTunnel() !== null => FishReception::STATUS_FROZEN,
            (float) $reception->getQuantiteConditionnee() > 0 || (float) $reception->getPoidsNet() > 0 || $reception->getProduitConditionne() !== null => FishReception::STATUS_PACKAGED,
            (float) $reception->getQuantiteTotalePreparee() > 0 => FishReception::STATUS_PROCESSING,
            $reception->getQuantiteReceptionneeValue() > 0 => FishReception::STATUS_RECEIVED,
            default => FishReception::STATUS_DRAFT,
        };

        $reception->setStatut($status);
    }

    private function assertQuantitiesCoherent(FishReception $reception): void
    {
        if ($reception->getQuantiteUtiliseeProductionValue() - $reception->getQuantiteReceptionneeValue() > 0.001) {
            throw new \DomainException('La quantite deja utilisee depasse la quantite receptionnee.');
        }

        if ($reception->getQuantiteTotalePrepareeValue() - $reception->getQuantiteReceptionneeValue() > 0.001) {
            throw new \DomainException('La quantite preparee ne peut pas depasser la quantite receptionnee.');
        }

        if ($reception->getQuantiteConditionneeValue() - $reception->getQuantiteTotalePrepareeValue() > 0.001) {
            throw new \DomainException('La quantite emballee ne peut pas depasser la quantite preparee.');
        }

        if ($reception->getQuantiteCongeleeValue() - $reception->getQuantiteConditionneeValue() > 0.001) {
            throw new \DomainException('La quantite congelee ne peut pas depasser la quantite emballee.');
        }

        if ($reception->getQuantiteStockeeValue() - $reception->getQuantiteCongeleeValue() > 0.001) {
            throw new \DomainException('La quantite stockee ne peut pas depasser la quantite congelee.');
        }

        if ($reception->getQuantiteTotaleExpedieeValue() - $reception->getQuantiteStockeeValue() > 0.001) {
            throw new \DomainException('La quantite expediee ne peut pas depasser la quantite stockee.');
        }
    }

    private function appendTreatmentCancelTrace(FishReception $reception, float $quantity, User $actor, ?string $reason): void
    {
        $line = sprintf(
            '[%s] Annulation traitement : %.3f kg remis en disponible reception par %s.',
            (new \DateTimeImmutable())->format('d/m/Y H:i'),
            $quantity,
            $actor->getDisplayName(),
        );

        $reason = trim((string) $reason);
        if ($reason !== '') {
            $line .= ' Motif : '.$reason;
        }

        $observations = trim((string) $reception->getObservations());
        $observations = $observations !== '' ? $observations."\n".$line : $line;

        if (mb_strlen($observations) > 1900) {
            $observations = '...'.mb_substr($observations, -1897);
        }

        $reception->setObservations($observations);
    }

    private function assertReceptionReady(FishReception $reception): void
    {
        if ($reception->isDeleted()) {
            throw new \DomainException('Reception introuvable.');
        }

        if ($reception->getStatut() === FishReception::STATUS_BLOCKED) {
            throw new \DomainException('Reception bloquee.');
        }

        if ($reception->isLocked()) {
            throw new \DomainException('Reception verrouillee.');
        }

        if ($reception->getQuantiteReceptionneeValue() <= 0) {
            throw new \DomainException('Renseignez la quantite receptionnee avant de continuer.');
        }

        if (!$reception->getFournisseur() || !$reception->getEspecePoisson()) {
            throw new \DomainException('Fournisseur et espece poisson sont obligatoires.');
        }
    }

    private function assertReceptionCanProvide(FishReception $reception, float $quantity, float $available): void
    {
        if ($quantity <= 0) {
            return;
        }

        if ($reception->isLocked() || $reception->getStatut() === FishReception::STATUS_DRAFT) {
            throw new \DomainException(sprintf('La reception %s est verrouillee ou non validee.', $reception->getNumeroReception()));
        }

        if ($quantity - $available > 0.001) {
            throw new \DomainException(sprintf(
                'Stock reception insuffisant : %.3f kg demandes, %.3f kg disponibles sur %s.',
                $quantity,
                max(0.0, $available),
                (string) $reception->getNumeroReception(),
            ));
        }
    }

    private function assertStageQuantity(FishReception $reception, float $quantity, float $available, string $sourceLabel): void
    {
        if ($quantity <= 0.001) {
            throw new \DomainException('Renseignez une quantite superieure a 0 kg.');
        }

        if ($quantity - $available > 0.001) {
            throw new \DomainException(sprintf(
                'Quantite insuffisante dans %s : %.3f kg demandes, %.3f kg disponibles sur %s.',
                $sourceLabel,
                $quantity,
                max(0.0, $available),
                (string) $reception->getNumeroReception(),
            ));
        }
    }

    private function denyUnlessAccess(User $actor): void
    {
        if (!$this->permission->canAccess($actor)) {
            throw new AccessDeniedException();
        }
    }

    private function denyUnlessTransition(User $actor, FishReception $reception): void
    {
        if (!$this->permission->canTransition($actor, $reception)) {
            throw new AccessDeniedException();
        }
    }

    private function nextReceptionNumber(): string
    {
        $prefix = sprintf('REC-%s-', (new \DateTimeImmutable())->format('Y'));
        $sequence = $this->repository->countByReceptionPrefix($prefix) + 1;

        do {
            $number = sprintf('%s%04d', $prefix, $sequence++);
        } while ($this->repository->findOneBy(['numeroReception' => $number]) instanceof FishReception);

        return $number;
    }

    private function nextLotNumber(): string
    {
        $prefix = sprintf('LOT-%s-', (new \DateTimeImmutable())->format('Y'));
        $sequence = $this->repository->countByLotPrefix($prefix) + 1;

        do {
            $number = sprintf('%s%04d', $prefix, $sequence++);
        } while ($this->repository->findOneBy(['numeroLot' => $number]) instanceof FishReception);

        return $number;
    }
}
