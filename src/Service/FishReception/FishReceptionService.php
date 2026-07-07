<?php

namespace App\Service\FishReception;

use App\Entity\CoutRevient;
use App\Entity\FishReception;
use App\Entity\FishReceptionStorageMovement;
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
        'congelation' => 'Congélation',
        'stockage' => 'Cristallisation chambre positive',
        'emballage' => 'Conditionnement / Emballage + remise en chambre',
        'expedition' => 'Expédition',
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
                'received' => 'Quantité reçue',
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
        $this->prepareWorkflowCorrection($reception);
        $this->assertInitialStorageLocationsCoherent($reception);
        $this->assertQuantitiesCoherent($reception);
        $this->assertWorkflowTraceabilityCoherent($reception);
        $this->autoRefreshStatus($reception);
        $this->entityManager->flush();

        return $reception;
    }

    public function correctWorkflowStage(FishReception $reception, User $actor): FishReception
    {
        if (!$this->permission->canEdit($actor, $reception)) {
            throw new AccessDeniedException();
        }

        $this->prepareWorkflowCorrection($reception);
        $this->assertInitialStorageLocationsCoherent($reception);
        $this->assertQuantitiesCoherent($reception);
        $this->autoRefreshStatus($reception);
        $this->entityManager->flush();

        return $reception;
    }

    public function updateInitialStorageMovement(FishReception $reception, FishReceptionStorageMovement $movement, User $actor, float $availableQuantity, string $originalLocation, float $originalQuantity): FishReception
    {
        if (!$this->permission->canEdit($actor, $reception)) {
            throw new AccessDeniedException();
        }

        if ($movement->getReception()?->getId() !== $reception->getId() || $movement->getStorageStage() !== FishReceptionStorageMovement::STAGE_INITIAL || $movement->getMovementType() !== FishReceptionStorageMovement::TYPE_INITIAL_ENTRY) {
            throw new \DomainException('Mouvement de stockage initial invalide.');
        }

        $quantity = $movement->getAbsoluteQuantityKgValue();
        $this->assertStageQuantity($reception, $quantity, $availableQuantity, 'la reception disponible pour cette ligne de stockage');
        if (trim($movement->getLocation()) === '') {
            throw new \DomainException('Selectionnez la chambre ou la zone de stockage initial.');
        }
        if (!$movement->getMovementDate() instanceof \DateTimeImmutable) {
            throw new \DomainException('Indiquez la date du stockage initial.');
        }

        $capacityQuantity = trim($movement->getLocation()) === trim($originalLocation)
            ? max(0.0, $quantity - $originalQuantity)
            : $quantity;
        if ($capacityQuantity > 0.001) {
            $this->factoryUnitService->assertStorageCanReceive($actor, $movement->getLocation(), $capacityQuantity);
        }
        $movement
            ->setQuantityKg($quantity)
            ->setUpdatedAt(new \DateTimeImmutable())
            ->setUpdatedBy($actor);

        $this->assertInitialStorageLocationsCoherent($reception);
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
        $sources = $reception->getStockInitialDisponibleParEmplacement();
        $sourceLocation = array_key_first($sources);
        if (!is_string($sourceLocation)) {
            throw new \DomainException('Stockez la reception en chambre avant de lancer le traitement.');
        }

        return $this->launchTreatment($reception, $reception->getQuantiteDisponibleTraitementSourceValue(), $actor, $sourceLocation);
    }

    public function registerInitialStorage(FishReception $reception, FishReceptionStorageMovement $movement, User $actor): FishReception
    {
        $this->denyUnlessTransition($actor, $reception);
        $this->assertReceptionReady($reception);

        $quantity = abs($movement->getQuantityKgValue());
        $this->assertStageQuantity($reception, $quantity, $reception->getQuantiteDisponibleStockageInitialValue(), 'la reception non stockee');
        if ($movement->getLocation() === '') {
            throw new \DomainException('Selectionnez la chambre ou la zone de stockage initial.');
        }

        $this->factoryUnitService->assertStorageCanReceive($actor, $movement->getLocation(), $quantity);

        $movement
            ->setReception($reception)
            ->setStorageStage(FishReceptionStorageMovement::STAGE_INITIAL)
            ->setMovementType(FishReceptionStorageMovement::TYPE_INITIAL_ENTRY)
            ->setQuantityKg($quantity)
            ->setCreatedBy($actor)
            ->setCreatedAt(new \DateTimeImmutable());

        if (!$movement->getMovementDate() instanceof \DateTimeImmutable) {
            $movement->setMovementDate(new \DateTimeImmutable('today'));
        }

        $reception->addStorageMovement($movement);
        $this->assertQuantitiesCoherent($reception);
        $this->entityManager->flush();

        return $reception;
    }

    public function launchTreatment(FishReception $reception, float $quantity, User $actor, ?string $sourceLocation = null): FishReception
    {
        $this->denyUnlessTransition($actor, $reception);
        $this->assertReceptionReady($reception);
        $sourceLocation = trim((string) $sourceLocation);
        if ($sourceLocation === '') {
            $sourceLocation = $this->selectInitialStorageSource($reception, $quantity);
        }

        $availableByLocation = $reception->getStockInitialDisponibleParEmplacement();
        $availableAtSource = $availableByLocation[$sourceLocation] ?? 0.0;
        $this->assertStageQuantity($reception, $quantity, $availableAtSource, 'le stock initial '.$sourceLocation);
        if ($reception->getStatut() === FishReception::STATUS_DRAFT) {
            $reception
                ->setReceivedAt($reception->getReceivedAt() ?? new \DateTimeImmutable())
                ->setReceivedBy($actor);
        }

        if (!$reception->getDateDebutTraitement()) {
            throw new \DomainException('Indiquez la date de debut traitement.');
        }
        if (!$reception->getHeureDebutTraitement()) {
            throw new \DomainException('Indiquez l\'heure de debut traitement.');
        }

        $now = new \DateTimeImmutable();
        $reception
            ->setQuantiteTotalePreparee($reception->getQuantiteTotalePrepareeValue() + $quantity)
            ->setStatut(FishReception::STATUS_PROCESSING)
            ->setTreatmentStartedAt($now)
            ->setTreatmentStartedBy($actor);

        $this->appendInitialStorageExit($reception, $quantity, $sourceLocation, $actor);
        $this->syncTreatmentBoxCounts($reception, $quantity);
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
        $this->appendInitialStorageReturn($reception, $quantity, $actor, $reason);
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
        $this->assertStageQuantity($reception, $quantity, $reception->getQuantiteDisponibleCristallisationValue(), 'la cristallisation');
        if (!$reception->getProduitConditionne()) {
            throw new \DomainException('Indiquez le produit conditionne avant de valider l\'emballage.');
        }
        if (!$reception->getDateConditionnement()) {
            throw new \DomainException('Indiquez la date d\'emballage.');
        }
        if (!$reception->getHeureDebutConditionnement()) {
            throw new \DomainException('Indiquez l\'heure de debut emballage.');
        }
        if (!$reception->getHeureFinConditionnement()) {
            throw new \DomainException('Indiquez l\'heure de fin emballage.');
        }
        if (!$reception->getChambreRemiseEnChambre()) {
            throw new \DomainException('Selectionnez la chambre ou le lot retourne apres emballage.');
        }
        if (!$reception->getDateRemiseEnChambre()) {
            throw new \DomainException('Indiquez la date de retour en chambre apres emballage.');
        }
        if (!$reception->getHeureRemiseEnChambre()) {
            throw new \DomainException('Indiquez l\'heure de retour en chambre apres emballage.');
        }
        $this->factoryUnitService->assertPositiveStorageCanReceive($actor, $reception->getChambreRemiseEnChambre(), $quantity);
        $now = new \DateTimeImmutable();

        $reception
            ->setQuantiteConditionnee($reception->getQuantiteConditionneeValue() + $quantity)
            ->setQuantiteRemiseEnChambre($reception->getQuantiteRemiseEnChambreValue() + $quantity)
            ->setStatut(FishReception::STATUS_RETURNED_TO_ROOM)
            ->setRemiseEnChambreAt($now)
            ->setRemiseEnChambreBy($actor)
            ->refreshCoutEmballage();

        $this->assertQuantitiesCoherent($reception);
        $this->entityManager->flush();

        return $reception;
    }

    public function registerFreezing(FishReception $reception, float $quantity, User $actor): FishReception
    {
        $this->denyUnlessTransition($actor, $reception);
        $this->assertReceptionReady($reception);
        if ($quantity > 0.001) {
            $this->assertStageQuantity($reception, $quantity, $reception->getQuantiteDisponibleTraitementValue(), 'le traitement');
        } elseif ($reception->getQuantiteDisponibleTraitementValue() > 0.001) {
            throw new \DomainException('Renseignez le produit fini a congeler ou completez les dechets/pertes du traitement.');
        }

        if ($quantity <= 0.001) {
            $this->assertQuantitiesCoherent($reception);
            $this->autoRefreshStatus($reception);
            $this->entityManager->flush();

            return $reception;
        }

        if (!$reception->getTunnel()) {
            throw new \DomainException('Selectionnez le tunnel avant de valider la congelation.');
        }
        if (!$reception->getDateEntreeTunnel()) {
            throw new \DomainException('Indiquez la date d\'entree tunnel pour tracer le mouvement.');
        }
        if (!$reception->getHeureEntreeTunnel()) {
            throw new \DomainException('Indiquez l\'heure d\'entree tunnel pour calculer la duree de congelation.');
        }
        $this->factoryUnitService->assertTunnelCanReceive($actor, $reception->getTunnel(), $quantity);

        $reception
            ->setQuantiteCongelee($reception->getQuantiteCongeleeValue() + $quantity)
            ->setStatut(FishReception::STATUS_FROZEN);

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
            throw new \DomainException('Selectionnez la chambre positive de cristallisation.');
        }
        if (!$reception->getDateEntreeTunnel()) {
            throw new \DomainException('Date d\'entree tunnel manquante. Renseignez-la a l\'etape congelation.');
        }
        if (!$reception->getHeureEntreeTunnel()) {
            throw new \DomainException('Heure d\'entree tunnel manquante. Renseignez-la a l\'etape congelation.');
        }
        if (!$reception->getDateSortieTunnel()) {
            throw new \DomainException('Indiquez la date de sortie tunnel avant l\'entree en chambre positive.');
        }
        if (!$reception->getHeureSortieTunnel()) {
            throw new \DomainException('Indiquez l\'heure de sortie tunnel avant l\'entree en chambre positive.');
        }
        if (!$reception->getDateEntreeStockage()) {
            throw new \DomainException('Indiquez la date d\'entree en chambre positive.');
        }
        if (!$reception->getHeureEntreeStockage()) {
            throw new \DomainException('Indiquez l\'heure d\'entree en chambre positive.');
        }
        $this->factoryUnitService->assertPositiveStorageCanReceive($actor, $reception->getChambreFroide(), $quantity);
        $now = new \DateTimeImmutable();

        $reception
            ->setQuantiteStockee($reception->getQuantiteStockeeValue() + $quantity)
            ->setStatut(FishReception::STATUS_STORED)
            ->setStoredAt($now)
            ->setStoredBy($actor);

        $this->assertQuantitiesCoherent($reception);
        $this->entityManager->flush();

        return $reception;
    }

    public function registerReturnStorage(FishReception $reception, float $quantity, User $actor): FishReception
    {
        $this->denyUnlessTransition($actor, $reception);
        $this->assertReceptionReady($reception);
        $this->assertStageQuantity($reception, $quantity, $reception->getQuantiteDisponibleEmballageValue(), 'l\'emballage');
        if (!$reception->getChambreRemiseEnChambre()) {
            throw new \DomainException('Selectionnez la chambre positive de retour apres emballage.');
        }
        if (!$reception->getDateRemiseEnChambre()) {
            throw new \DomainException('Indiquez la date de remise en chambre.');
        }
        if (!$reception->getHeureRemiseEnChambre()) {
            throw new \DomainException('Indiquez l\'heure de remise en chambre.');
        }
        $this->factoryUnitService->assertPositiveStorageCanReceive($actor, $reception->getChambreRemiseEnChambre(), $quantity);
        $now = new \DateTimeImmutable();

        $reception
            ->setQuantiteRemiseEnChambre($reception->getQuantiteRemiseEnChambreValue() + $quantity)
            ->setStatut(FishReception::STATUS_RETURNED_TO_ROOM)
            ->setRemiseEnChambreAt($now)
            ->setRemiseEnChambreBy($actor);

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
            throw new \DomainException('Indiquez la destination ou le client avant de valider l\'expédition.');
        }
        if ($reception->getExpeditionDateDepart() === null) {
            throw new \DomainException('Indiquez la date de depart expedition.');
        }
        if ($reception->getExpeditionHeureDepart() === null) {
            throw new \DomainException('Indiquez l\'heure de depart expedition.');
        }
        $now = new \DateTimeImmutable();
        if (!$reception->getExpeditionMatriculeVehicule()) {
            throw new \DomainException('Indiquez le matricule du camion avant de valider l\'expédition.');
        }
        if (!$reception->getExpeditionChauffeur()) {
            throw new \DomainException('Indiquez le nom du chauffeur avant de valider l\'expédition.');
        }
        if (!$reception->getExpeditionResponsableChargement()) {
            throw new \DomainException('Indiquez le responsable du chargement avant de valider l\'expédition.');
        }
        if ($reception->getDateRemiseEnChambre() instanceof \DateTimeImmutable && $reception->getExpeditionDateDepart()->format('Y-m-d') < $reception->getDateRemiseEnChambre()->format('Y-m-d')) {
            throw new \DomainException('La date d\'expedition ne peut pas etre avant la remise en chambre apres emballage.');
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

        if ($reception->getStorageMovements()->count() > 0 || $reception->getQuantiteTotalePrepareeValue() > 0.001 || $reception->getQuantiteCongeleeValue() > 0.001 || $reception->getQuantiteStockeeValue() > 0.001 || $reception->getQuantiteConditionneeValue() > 0.001 || $reception->getQuantiteRemiseEnChambreValue() > 0.001 || $reception->getQuantiteTotaleExpedieeValue() > 0.001 || $reception->getQuantiteUtiliseeProductionValue() > 0.001 || $reception->getCoutRevients()->count() > 0) {
            throw new \DomainException('Impossible de supprimer une réception déjà utilisée dans le workflow ou rattachée à un lot.');
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
            (float) $reception->getQuantiteRemiseEnChambre() > 0 || $reception->getRemiseEnChambreAt() !== null || $reception->getChambreRemiseEnChambre() !== null => FishReception::STATUS_RETURNED_TO_ROOM,
            (float) $reception->getQuantiteConditionnee() > 0 || (float) $reception->getPoidsNet() > 0 || $reception->getPoidsDechetsEmballageValue() > 0 || $reception->getPoidsPertesEmballageValue() > 0 || $reception->getProduitConditionne() !== null => FishReception::STATUS_PACKAGED,
            (float) $reception->getQuantiteStockee() > 0 || $reception->getChambreFroide() !== null => FishReception::STATUS_STORED,
            (float) $reception->getQuantiteCongelee() > 0 || $reception->getTunnel() !== null => FishReception::STATUS_FROZEN,
            (float) $reception->getQuantiteTotalePreparee() > 0 => FishReception::STATUS_PROCESSING,
            $reception->getQuantiteReceptionneeValue() > 0 => FishReception::STATUS_RECEIVED,
            default => FishReception::STATUS_DRAFT,
        };

        $reception->setStatut($status);
    }

    private function prepareWorkflowCorrection(FishReception $reception): void
    {
        $reception->refreshCoutEmballage();
    }

    private function assertInitialStorageLocationsCoherent(FishReception $reception): void
    {
        $stocks = [];
        foreach ($reception->getStorageMovements() as $movement) {
            if ($movement->getStorageStage() !== FishReceptionStorageMovement::STAGE_INITIAL) {
                continue;
            }

            $location = trim($movement->getLocation());
            if ($location === '') {
                continue;
            }

            $stocks[$location] = ($stocks[$location] ?? 0.0) + $movement->getQuantityKgValue();
        }

        foreach ($stocks as $location => $quantity) {
            if ($quantity < -0.001) {
                throw new \DomainException(sprintf('Stock initial incoherent pour %s : les sorties traitement depassent les entrees de %.3f kg.', $location, abs($quantity)));
            }
        }
    }

    private function assertWorkflowTraceabilityCoherent(FishReception $reception): void
    {
        if ($reception->getQuantiteTotalePrepareeValue() > 0.001 && (!$reception->getDateDebutTraitement() || !$reception->getHeureDebutTraitement())) {
            throw new \DomainException('La correction traitement doit garder une date et une heure de debut traitement.');
        }

        if ($reception->getQuantiteCongeleeValue() > 0.001 && (!$reception->getTunnel() || !$reception->getDateEntreeTunnel() || !$reception->getHeureEntreeTunnel())) {
            throw new \DomainException('La correction congelation doit garder le tunnel, la date et l heure d entree tunnel.');
        }

        if ($reception->getQuantiteStockeeValue() > 0.001 && (!$reception->getChambreFroide() || !$reception->getDateSortieTunnel() || !$reception->getHeureSortieTunnel() || !$reception->getDateEntreeStockage() || !$reception->getHeureEntreeStockage())) {
            throw new \DomainException('La correction cristallisation doit garder la sortie tunnel et l entree en chambre.');
        }

        if ($reception->getQuantiteConditionneeValue() > 0.001 && (!$reception->getProduitConditionne() || !$reception->getDateConditionnement() || !$reception->getHeureDebutConditionnement() || !$reception->getHeureFinConditionnement() || !$reception->getChambreRemiseEnChambre() || !$reception->getDateRemiseEnChambre() || !$reception->getHeureRemiseEnChambre())) {
            throw new \DomainException('La correction emballage doit garder les heures emballage et la chambre de retour.');
        }

        if ($reception->getQuantiteTotaleExpedieeValue() > 0.001 && (!$reception->getDestinationFinaleClient() || !$reception->getExpeditionDateDepart() || !$reception->getExpeditionHeureDepart() || !$reception->getExpeditionMatriculeVehicule() || !$reception->getExpeditionChauffeur() || !$reception->getExpeditionResponsableChargement())) {
            throw new \DomainException('La correction expedition doit garder client, date, heure, camion, chauffeur et responsable chargement.');
        }
    }

    private function assertQuantitiesCoherent(FishReception $reception): void
    {
        if ($reception->getQuantiteStockInitialEntreeValue() - $reception->getQuantiteReceptionneeValue() > 0.001) {
            throw new \DomainException('La quantite stockee initialement ne peut pas depasser la quantite receptionnee.');
        }

        if ($reception->getStorageMovements()->count() > 0 && $reception->getQuantiteTotalePrepareeValue() - $reception->getQuantiteStockInitialSortieValue() > 0.001) {
            throw new \DomainException('La quantite preparee doit provenir du stock initial sorti vers traitement.');
        }

        if ($reception->getQuantiteUtiliseeProductionValue() - $reception->getQuantiteReceptionneeValue() > 0.001) {
            throw new \DomainException('La quantité déjà utilisée dépasse la quantité réceptionnée.');
        }

        if ($reception->getQuantiteTotalePrepareeValue() - $reception->getQuantiteReceptionneeValue() > 0.001) {
            throw new \DomainException('La quantité préparée ne peut pas dépasser la quantité réceptionnée.');
        }

        if ($reception->getTotalSortieTraitementValue() - $reception->getQuantiteEnTraitementValue() > 0.001) {
            throw new \DomainException('Le total produit fini + dechets + pertes ne peut pas depasser la quantite en traitement.');
        }

        if ($reception->getQuantiteCongeleeValue() - $reception->getQuantiteTotalePrepareeValue() > 0.001) {
            throw new \DomainException('La quantite congelee ne peut pas depasser la quantite preparee.');
        }

        if ($reception->getQuantiteStockeeValue() - $reception->getQuantiteCongeleeValue() > 0.001) {
            throw new \DomainException('La quantite en cristallisation ne peut pas depasser la quantite congelee.');
        }

        if ($reception->getQuantiteConditionneeValue() - $reception->getQuantiteStockeeValue() > 0.001) {
            throw new \DomainException('La quantite emballee ne peut pas depasser la quantite cristallisee.');
        }

        if ($reception->getQuantiteRemiseEnChambreValue() - $reception->getQuantiteConditionneeValue() > 0.001) {
            throw new \DomainException('La quantite remise en chambre ne peut pas depasser la quantite emballee.');
        }

        if ($reception->getQuantiteTotaleExpedieeValue() - $reception->getQuantiteExpediableValue() > 0.001) {
            throw new \DomainException('La quantite expediee ne peut pas depasser le poids net disponible apres emballage.');
        }
    }

    private function appendInitialStorageExit(FishReception $reception, float $quantity, string $sourceLocation, User $actor): void
    {
        $movement = (new FishReceptionStorageMovement())
            ->setReception($reception)
            ->setStorageStage(FishReceptionStorageMovement::STAGE_INITIAL)
            ->setMovementType(FishReceptionStorageMovement::TYPE_INITIAL_EXIT)
            ->setLocation($sourceLocation)
            ->setQuantityKg(-abs($quantity))
            ->setMovementDate($reception->getDateDebutTraitement() ?? new \DateTimeImmutable('today'))
            ->setMovementTime($reception->getHeureDebutTraitement() ?? new \DateTimeImmutable())
            ->setNote('Sortie vers traitement / production')
            ->setCreatedBy($actor)
            ->setCreatedAt(new \DateTimeImmutable());

        $reception->addStorageMovement($movement);
    }

    private function selectInitialStorageSource(FishReception $reception, float $quantity): string
    {
        $fallback = null;
        foreach ($reception->getStockInitialDisponibleParEmplacement() as $location => $available) {
            $fallback ??= $location;
            if ($available + 0.001 >= $quantity) {
                return $location;
            }
        }

        if (is_string($fallback)) {
            return $fallback;
        }

        throw new \DomainException('Stockez la reception en chambre avant de lancer le traitement.');
    }

    private function appendInitialStorageReturn(FishReception $reception, float $quantity, User $actor, ?string $reason): void
    {
        $lastExit = null;
        foreach ($reception->getStorageMovements() as $movement) {
            if ($movement->getMovementType() === FishReceptionStorageMovement::TYPE_INITIAL_EXIT) {
                $lastExit = $movement;
            }
        }

        $location = $lastExit instanceof FishReceptionStorageMovement ? $lastExit->getLocation() : 'Stock initial';
        $note = 'Retour apres annulation traitement';
        $reason = trim((string) $reason);
        if ($reason !== '') {
            $note .= ' : '.$reason;
        }

        $movement = (new FishReceptionStorageMovement())
            ->setReception($reception)
            ->setStorageStage(FishReceptionStorageMovement::STAGE_INITIAL)
            ->setMovementType(FishReceptionStorageMovement::TYPE_INITIAL_RETURN)
            ->setLocation($location)
            ->setQuantityKg(abs($quantity))
            ->setMovementDate(new \DateTimeImmutable('today'))
            ->setMovementTime(new \DateTimeImmutable())
            ->setNote($note)
            ->setCreatedBy($actor)
            ->setCreatedAt(new \DateTimeImmutable());

        $reception->addStorageMovement($movement);
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

    private function syncTreatmentBoxCounts(FishReception $reception, float $quantity): void
    {
        $boxWeight = (float) $reception->getPoidsMoyenParCaisse();
        if ($quantity <= 0.001 || $boxWeight <= 0.001) {
            $reception
                ->setNombreCaissesApresTraitement(0)
                ->setNombreTotalPalettes(0);

            return;
        }

        $boxCount = (int) ceil($quantity / $boxWeight);
        $reception->setNombreCaissesApresTraitement($boxCount);

        $boxesPerPallet = $reception->getNombreCaissesParPalette();
        $reception->setNombreTotalPalettes($boxesPerPallet > 0 ? (int) ceil($boxCount / $boxesPerPallet) : 0);
    }

    private function assertReceptionReady(FishReception $reception): void
    {
        if ($reception->isDeleted()) {
            throw new \DomainException('Reception introuvable.');
        }

        if ($reception->getStatut() === FishReception::STATUS_BLOCKED) {
            throw new \DomainException('Réception bloquée.');
        }

        if ($reception->isLocked()) {
            throw new \DomainException('Reception verrouillée.');
        }

        if ($reception->getQuantiteReceptionneeValue() <= 0) {
            throw new \DomainException('Renseignez la quantité réceptionnée avant de continuer.');
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
            throw new \DomainException(sprintf('La reception %s est verrouillée ou non validée.', $reception->getNumeroReception()));
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
            throw new \DomainException('Renseignez une quantité supérieure à 0 kg.');
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
