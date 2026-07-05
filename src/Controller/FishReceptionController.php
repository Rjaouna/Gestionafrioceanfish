<?php

namespace App\Controller;

use App\Entity\FishReception;
use App\Entity\User;
use App\Form\FishReceptionFreezingType;
use App\Form\FishReceptionPackagingType;
use App\Form\FishReceptionShippingType;
use App\Form\FishReceptionStorageType;
use App\Form\FishReceptionTreatmentCancelType;
use App\Form\FishReceptionTreatmentType;
use App\Form\FishReceptionType;
use App\Security\Voter\FishReceptionVoter;
use App\Security\Voter\ModuleAccessVoter;
use App\Service\FactoryUnitService;
use App\Service\FishReception\FishReceptionExcelFormService;
use App\Service\FishReception\FishReceptionService;
use App\Service\JsonResponder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Twig\Environment;

#[Route('/receptions')]
#[IsGranted('ROLE_USER')]
final class FishReceptionController extends AbstractController
{
    public function __construct(
        private readonly FishReceptionService $receptionService,
        private readonly FactoryUnitService $factoryUnitService,
        private readonly FishReceptionExcelFormService $excelFormService,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
        private readonly JsonResponder $jsonResponder,
        private readonly Environment $twig,
    ) {
    }

    #[Route('', name: 'app_fish_reception_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $this->denyAccessUnlessGranted(ModuleAccessVoter::ACCESS, 'receptions');
        $filters = $this->filtersFromRequest($request);
        $result = $this->receptionService->search($this->currentUser(), $filters, $request->query->getInt('page', 1));

        return $this->render('fish_reception/index.html.twig', [
            'items' => $result['items'],
            'pagination' => $result,
            'filters' => $result['filters'],
            'filter_choices' => $this->filterChoices(),
            'stats' => $this->receptionService->dashboard($this->currentUser(), $filters),
            'factory_storage_overview' => $this->factoryUnitService->storageOverview($this->currentUser()),
            'stage' => 'reception',
            'stage_config' => $this->stageConfig('reception'),
            'refresh_url' => $this->generateUrl('app_fish_reception_search'),
            'clear_url' => $this->generateUrl('app_fish_reception_index'),
        ]);
    }

    #[Route('/etape/{stage}', name: 'app_fish_reception_stage', requirements: ['stage' => 'reception|traitement|emballage|congelation|stockage|expedition'], methods: ['GET'])]
    public function stage(Request $request, string $stage): Response
    {
        $this->denyAccessUnlessGranted(ModuleAccessVoter::ACCESS, 'receptions');
        $filters = $this->filtersFromRequest($request, $stage);
        $result = $this->receptionService->search($this->currentUser(), $filters, $request->query->getInt('page', 1));

        return $this->render('fish_reception/index.html.twig', [
            'items' => $result['items'],
            'pagination' => $result,
            'filters' => $result['filters'],
            'filter_choices' => $this->filterChoices(),
            'stats' => $this->receptionService->dashboard($this->currentUser(), $filters),
            'factory_storage_overview' => $this->factoryUnitService->storageOverview($this->currentUser()),
            'stage' => $stage,
            'stage_config' => $this->stageConfig($stage),
            'refresh_url' => $this->generateUrl('app_fish_reception_stage_search', ['stage' => $stage]),
            'clear_url' => $stage === 'reception' ? $this->generateUrl('app_fish_reception_index') : $this->generateUrl('app_fish_reception_stage', ['stage' => $stage]),
        ]);
    }

    #[Route('/ajax/list', name: 'app_fish_reception_search', methods: ['GET'])]
    public function search(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(ModuleAccessVoter::ACCESS, 'receptions');
        $result = $this->receptionService->search($this->currentUser(), $this->filtersFromRequest($request), $request->query->getInt('page', 1));

        return $this->jsonResponder->success('Liste mise a jour.', [
            'html' => $this->renderView('fish_reception/_grid.html.twig', [
                'items' => $result['items'],
                'pagination' => $result,
                'stage' => 'reception',
            ]),
            'count' => $result['total'],
            'page' => $result['page'],
            'pages' => $result['pages'],
        ]);
    }

    #[Route('/ajax/etape/{stage}', name: 'app_fish_reception_stage_search', requirements: ['stage' => 'reception|traitement|emballage|congelation|stockage|expedition'], methods: ['GET'])]
    public function stageSearch(Request $request, string $stage): JsonResponse
    {
        $this->denyAccessUnlessGranted(ModuleAccessVoter::ACCESS, 'receptions');
        $result = $this->receptionService->search($this->currentUser(), $this->filtersFromRequest($request, $stage), $request->query->getInt('page', 1));

        return $this->jsonResponder->success('Liste mise a jour.', [
            'html' => $this->renderView('fish_reception/_grid.html.twig', [
                'items' => $result['items'],
                'pagination' => $result,
                'stage' => $stage,
            ]),
            'count' => $result['total'],
            'page' => $result['page'],
            'pages' => $result['pages'],
        ]);
    }

    #[Route('/ajax/usine/etat', name: 'app_fish_reception_factory_overview', methods: ['GET'])]
    public function factoryOverview(): JsonResponse
    {
        $this->denyAccessUnlessGranted(ModuleAccessVoter::ACCESS, 'receptions');

        return $this->jsonResponder->success('Etat usine mis a jour.', [
            'html' => $this->renderView('fish_reception/_factory_storage_overview.html.twig', [
                'factory_storage_overview' => $this->factoryUnitService->storageOverview($this->currentUser()),
            ]),
        ]);
    }

    #[Route('/nouveau', name: 'app_fish_reception_new', methods: ['GET'])]
    public function new(): Response
    {
        $this->denyAccessUnlessGranted(FishReceptionVoter::CREATE);
        $reception = new FishReception();

        return $this->render('fish_reception/new.html.twig', [
            'form' => $this->buildForm($reception, 'app_fish_reception_create'),
            'item' => $reception,
            'title' => 'Nouvelle reception',
            'submit_label' => 'Enregistrer la reception',
            'excel_template_url' => $this->generateUrl('app_fish_reception_excel_template', ['stage' => 'reception']),
            'excel_import_url' => $this->generateUrl('app_fish_reception_excel_import', ['stage' => 'reception']),
            'excel_import_token' => $this->excelImportToken('reception'),
        ]);
    }

    #[Route('/excel/{stage}/modele', name: 'app_fish_reception_excel_template', requirements: ['stage' => 'reception|traitement|emballage|congelation|stockage|expedition'], methods: ['GET'])]
    public function excelTemplate(string $stage): BinaryFileResponse
    {
        $this->denyAccessUnlessGranted(ModuleAccessVoter::ACCESS, 'receptions');
        if ($stage !== 'reception') {
            throw $this->createNotFoundException('Cette phase necessite une reception existante.');
        }

        return $this->downloadExcelTemplate($stage, null);
    }

    #[Route('/excel/{stage}/import', name: 'app_fish_reception_excel_import', requirements: ['stage' => 'reception|traitement|emballage|congelation|stockage|expedition'], methods: ['POST'])]
    public function importExcel(string $stage, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(ModuleAccessVoter::ACCESS, 'receptions');
        if ($stage !== 'reception') {
            return $this->jsonResponder->error('Cette phase necessite une reception existante.', [], 404);
        }

        return $this->importExcelTemplate($stage, $request, null);
    }

    #[Route('/nouveau', name: 'app_fish_reception_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(FishReceptionVoter::CREATE);
        $reception = new FishReception();
        $form = $this->buildForm($reception, 'app_fish_reception_create');
        $form->handleRequest($request);
        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->jsonResponder->invalidForm($form);
        }

        try {
            $this->receptionService->create($reception, $this->currentUser());
        } catch (\DomainException $exception) {
            return $this->jsonResponder->error($exception->getMessage(), [], 422);
        }

        return $this->jsonResponder->success('Reception enregistree.', [
            'redirectUrl' => $this->generateUrl('app_fish_reception_view', ['id' => $reception->getId()]),
        ], 201);
    }

    #[Route('/{id}/voir', name: 'app_fish_reception_view', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function view(FishReception $reception, Request $request): Response
    {
        $this->denyAccessUnlessGranted(FishReceptionVoter::VIEW, $reception);

        if ($request->isXmlHttpRequest()) {
            return $this->render('fish_reception/_details_modal.html.twig', ['item' => $reception]);
        }

        return $this->render('fish_reception/show.html.twig', ['item' => $reception]);
    }

    #[Route('/{id}/voir/fragment', name: 'app_fish_reception_view_fragment', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function viewFragment(FishReception $reception): JsonResponse
    {
        $this->denyAccessUnlessGranted(FishReceptionVoter::VIEW, $reception);

        return $this->jsonResponder->success('Fiche reception mise a jour.', [
            'html' => $this->twig->load('fish_reception/show.html.twig')->renderBlock('reception_show_content', [
                'item' => $reception,
            ]),
        ]);
    }

    #[Route('/{id}/modifier', name: 'app_fish_reception_edit', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function editForm(FishReception $reception): Response
    {
        $this->denyAccessUnlessGranted(FishReceptionVoter::EDIT, $reception);

        return $this->render('fish_reception/edit.html.twig', [
            'form' => $this->buildForm($reception, 'app_fish_reception_update', ['id' => $reception->getId()]),
            'item' => $reception,
            'title' => sprintf('Modifier %s', $reception->getNumeroReception()),
            'submit_label' => 'Enregistrer',
            'excel_template_url' => $this->generateUrl('app_fish_reception_excel_template_item', ['id' => $reception->getId(), 'stage' => 'reception']),
            'excel_import_url' => $this->generateUrl('app_fish_reception_excel_import_item', ['id' => $reception->getId(), 'stage' => 'reception']),
            'excel_import_token' => $this->excelImportToken('reception', $reception),
        ]);
    }

    #[Route('/{id}/excel/{stage}/modele', name: 'app_fish_reception_excel_template_item', requirements: ['id' => '\d+', 'stage' => 'reception|traitement|emballage|congelation|stockage|expedition'], methods: ['GET'])]
    public function excelTemplateItem(FishReception $reception, string $stage): BinaryFileResponse
    {
        $this->denyAccessUnlessGranted(FishReceptionVoter::VIEW, $reception);

        return $this->downloadExcelTemplate($stage, $reception);
    }

    #[Route('/{id}/excel/{stage}/import', name: 'app_fish_reception_excel_import_item', requirements: ['id' => '\d+', 'stage' => 'reception|traitement|emballage|congelation|stockage|expedition'], methods: ['POST'])]
    public function importExcelItem(FishReception $reception, string $stage, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(FishReceptionVoter::VIEW, $reception);

        return $this->importExcelTemplate($stage, $request, $reception);
    }

    #[Route('/{id}/modifier', name: 'app_fish_reception_update', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function update(FishReception $reception, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(FishReceptionVoter::EDIT, $reception);
        $form = $this->buildForm($reception, 'app_fish_reception_update', ['id' => $reception->getId()]);
        $form->handleRequest($request);
        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->jsonResponder->invalidForm($form);
        }

        try {
            $this->receptionService->update($reception, $this->currentUser());
        } catch (\DomainException $exception) {
            return $this->jsonResponder->error($exception->getMessage(), [], 422);
        }

        return $this->jsonResponder->success('Reception mise a jour.', [
            'redirectUrl' => $this->generateUrl('app_fish_reception_view', ['id' => $reception->getId()]),
        ]);
    }

    #[Route('/{id}/valider', name: 'app_fish_reception_validate', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function validateReception(FishReception $reception, Request $request): JsonResponse
    {
        return $this->transition($reception, $request, 'validate_fish_reception_', 'Reception validee.', fn () => $this->receptionService->validateReception($reception, $this->currentUser()));
    }

    #[Route('/{id}/traitement', name: 'app_fish_reception_start_treatment', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function startTreatment(FishReception $reception, Request $request): JsonResponse
    {
        return $this->transition($reception, $request, 'treatment_fish_reception_', 'Reception envoyee en traitement.', fn () => $this->receptionService->startTreatment($reception, $this->currentUser()));
    }

    #[Route('/{id}/traitement/formulaire', name: 'app_fish_reception_treatment_form', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function treatmentForm(FishReception $reception): Response
    {
        return $this->renderStageModal(
            $reception,
            FishReceptionTreatmentType::class,
            'app_fish_reception_launch_treatment',
            'Lancer le traitement',
            'Cette quantite sera deduite du disponible reception et ajoutee au traitement.',
            'bi-arrow-repeat',
            'btn-info',
            $reception->getQuantiteDisponibleReceptionValue(),
        );
    }

    #[Route('/{id}/traitement/lancer', name: 'app_fish_reception_launch_treatment', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function launchTreatment(FishReception $reception, Request $request): JsonResponse
    {
        return $this->handleStageAction(
            $reception,
            $request,
            FishReceptionTreatmentType::class,
            'app_fish_reception_launch_treatment',
            $reception->getQuantiteDisponibleReceptionValue(),
            fn (float $quantity) => $this->receptionService->launchTreatment($reception, $quantity, $this->currentUser()),
            'Quantite envoyee au traitement.',
        );
    }

    #[Route('/{id}/traitement/annulation/formulaire', name: 'app_fish_reception_cancel_treatment_form', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function cancelTreatmentForm(FishReception $reception): Response
    {
        return $this->renderStageModal(
            $reception,
            FishReceptionTreatmentCancelType::class,
            'app_fish_reception_cancel_treatment',
            'Annuler du traitement',
            'Cette quantite sera retiree du traitement et remise dans le disponible reception.',
            'bi-arrow-counterclockwise',
            'btn-danger',
            $reception->getQuantiteDisponibleTraitementValue(),
        );
    }

    #[Route('/{id}/traitement/annuler', name: 'app_fish_reception_cancel_treatment', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function cancelTreatment(FishReception $reception, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(FishReceptionVoter::TRANSITION, $reception);
        $form = $this->buildStageForm(
            $reception,
            FishReceptionTreatmentCancelType::class,
            'app_fish_reception_cancel_treatment',
            $reception->getQuantiteDisponibleTraitementValue(),
        );
        $form->handleRequest($request);
        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->jsonResponder->invalidForm($form);
        }

        try {
            $this->receptionService->cancelTreatment(
                $reception,
                $this->quantityFromForm($form),
                $this->currentUser(),
                (string) $form->get('reason')->getData(),
            );
        } catch (\DomainException $exception) {
            return $this->jsonResponder->error($exception->getMessage(), [], 422);
        }

        return $this->jsonResponder->success('Quantite retiree du traitement et remise en disponible reception.', [
            'closeModal' => true,
            'refreshRegions' => ['fishReceptionGrid', 'fishReceptionFactoryOverview', 'fishReceptionShow'],
        ]);
    }

    #[Route('/{id}/congelation/formulaire', name: 'app_fish_reception_freezing_form', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function freezingForm(FishReception $reception): Response
    {
        return $this->renderStageModal(
            $reception,
            FishReceptionFreezingType::class,
            'app_fish_reception_register_freezing',
            'Valider la congelation',
            "Cette quantite sera deduite de l'emballage et ajoutee a la congelation.",
            'bi-snow',
            'btn-primary',
            $reception->getQuantiteDisponibleEmballageValue(),
        );
    }

    #[Route('/{id}/congelation/capacite-tunnel', name: 'app_fish_reception_freezing_capacity_check', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function freezingCapacityCheck(FishReception $reception, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(FishReceptionVoter::TRANSITION, $reception);

        $quantity = (float) str_replace(',', '.', (string) $request->query->get('quantity', '0'));

        return $this->jsonResponder->success('Capacite tunnel verifiee.', $this->factoryUnitService->tunnelCapacityDiagnostic(
            $this->currentUser(),
            (string) $request->query->get('tunnel', $request->query->get('location', '')),
            $quantity,
        ));
    }

    #[Route('/{id}/congelation/enregistrer', name: 'app_fish_reception_register_freezing', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function registerFreezing(FishReception $reception, Request $request): JsonResponse
    {
        return $this->handleStageAction(
            $reception,
            $request,
            FishReceptionFreezingType::class,
            'app_fish_reception_register_freezing',
            $reception->getQuantiteDisponibleEmballageValue(),
            fn (float $quantity) => $this->receptionService->registerFreezing($reception, $quantity, $this->currentUser()),
            'Quantite congelee enregistree.',
        );
    }

    #[Route('/{id}/stockage', name: 'app_fish_reception_store', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function store(FishReception $reception, Request $request): JsonResponse
    {
        return $this->transition($reception, $request, 'store_fish_reception_', 'Reception marquee en stock.', fn () => $this->receptionService->markStored($reception, $this->currentUser()));
    }

    #[Route('/{id}/stockage/formulaire', name: 'app_fish_reception_storage_form', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function storageForm(FishReception $reception): Response
    {
        return $this->renderStageModal(
            $reception,
            FishReceptionStorageType::class,
            'app_fish_reception_register_storage',
            'Entrer en stockage',
            'Cette quantite sera deduite de la congelation et ajoutee au stock chambre froide.',
            'bi-box-seam',
            'btn-success',
            $reception->getQuantiteDisponibleCongelationValue(),
        );
    }

    #[Route('/{id}/stockage/capacite-espace', name: 'app_fish_reception_storage_capacity_check', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function storageCapacityCheck(FishReception $reception, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(FishReceptionVoter::TRANSITION, $reception);

        $quantity = (float) str_replace(',', '.', (string) $request->query->get('quantity', '0'));

        return $this->jsonResponder->success('Capacite espace stockage verifiee.', $this->factoryUnitService->storageCapacityDiagnostic(
            $this->currentUser(),
            (string) $request->query->get('location', $request->query->get('chambreFroide', '')),
            $quantity,
        ));
    }

    #[Route('/{id}/stockage/enregistrer', name: 'app_fish_reception_register_storage', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function registerStorage(FishReception $reception, Request $request): JsonResponse
    {
        return $this->handleStageAction(
            $reception,
            $request,
            FishReceptionStorageType::class,
            'app_fish_reception_register_storage',
            $reception->getQuantiteDisponibleCongelationValue(),
            fn (float $quantity) => $this->receptionService->registerStorage($reception, $quantity, $this->currentUser()),
            'Quantite entree en stock.',
        );
    }

    #[Route('/{id}/emballage/formulaire', name: 'app_fish_reception_packaging_form', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function packagingForm(FishReception $reception): Response
    {
        return $this->renderStageModal(
            $reception,
            FishReceptionPackagingType::class,
            'app_fish_reception_register_packaging',
            'Enregistrer emballage',
            'Cette quantite sera deduite du traitement et ajoutee au conditionnement.',
            'bi-box',
            'btn-warning',
            $reception->getQuantiteDisponibleTraitementValue(),
        );
    }

    #[Route('/{id}/emballage/enregistrer', name: 'app_fish_reception_register_packaging', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function registerPackaging(FishReception $reception, Request $request): JsonResponse
    {
        return $this->handleStageAction(
            $reception,
            $request,
            FishReceptionPackagingType::class,
            'app_fish_reception_register_packaging',
            $reception->getQuantiteDisponibleTraitementValue(),
            fn (float $quantity) => $this->receptionService->registerPackaging($reception, $quantity, $this->currentUser()),
            'Quantite emballee enregistree.',
        );
    }

    #[Route('/{id}/expedition/formulaire', name: 'app_fish_reception_shipping_form', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function shippingForm(FishReception $reception): Response
    {
        return $this->renderStageModal(
            $reception,
            FishReceptionShippingType::class,
            'app_fish_reception_register_shipping',
            'Enregistrer expedition',
            'Cette quantite sera deduite du stock et ajoutee aux expeditions.',
            'bi-truck',
            'btn-dark',
            $reception->getQuantiteDisponibleStockageValue(),
        );
    }

    #[Route('/{id}/expedition/enregistrer', name: 'app_fish_reception_register_shipping', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function registerShipping(FishReception $reception, Request $request): JsonResponse
    {
        return $this->handleStageAction(
            $reception,
            $request,
            FishReceptionShippingType::class,
            'app_fish_reception_register_shipping',
            $reception->getQuantiteDisponibleStockageValue(),
            fn (float $quantity) => $this->receptionService->registerShipping($reception, $quantity, $this->currentUser()),
            'Quantite expediee enregistree.',
        );
    }

    #[Route('/{id}/cloturer', name: 'app_fish_reception_close', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function close(FishReception $reception, Request $request): JsonResponse
    {
        return $this->transition($reception, $request, 'close_fish_reception_', 'Reception cloturee et verrouillee.', fn () => $this->receptionService->close($reception, $this->currentUser()));
    }

    #[Route('/{id}/bloquer/formulaire', name: 'app_fish_reception_block_form', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function blockForm(FishReception $reception): Response
    {
        $this->denyAccessUnlessGranted(FishReceptionVoter::TRANSITION, $reception);

        return $this->render('fish_reception/_block_modal.html.twig', ['item' => $reception]);
    }

    #[Route('/{id}/bloquer', name: 'app_fish_reception_block', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function block(FishReception $reception, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(FishReceptionVoter::TRANSITION, $reception);
        try {
            $this->assertCsrf((string) $request->request->get('token'), 'block_fish_reception_'.$reception->getId());
            $this->receptionService->block($reception, $this->currentUser(), (string) $request->request->get('reason'));
        } catch (\DomainException $exception) {
            return $this->jsonResponder->error($exception->getMessage(), [], 422);
        }

        return $this->jsonResponder->success('Reception bloquee.', [
            'closeModal' => true,
            'refreshRegions' => ['fishReceptionGrid', 'fishReceptionFactoryOverview', 'fishReceptionShow'],
        ]);
    }

    #[Route('/{id}/supprimer', name: 'app_fish_reception_delete', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    public function delete(FishReception $reception, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(FishReceptionVoter::DELETE, $reception);
        $payload = $request->toArray();
        try {
            $this->assertCsrf((string) ($payload['token'] ?? ''), 'delete_fish_reception_'.$reception->getId());
            $movedToTrash = $this->receptionService->delete($reception, $this->currentUser());
        } catch (\DomainException $exception) {
            return $this->jsonResponder->error($exception->getMessage(), [], 422);
        }

        return $this->jsonResponder->success(
            $movedToTrash ? 'Reception deplacee dans la corbeille.' : 'Reception supprimee.',
            ['reload' => true],
        );
    }

    /** @param array<string, int|string|null> $routeParameters */
    private function buildForm(FishReception $reception, string $route, array $routeParameters = []): FormInterface
    {
        return $this->createForm(FishReceptionType::class, $reception, [
            'action' => $this->generateUrl($route, $routeParameters),
            'choice_lists' => $this->receptionService->formChoiceLists($this->currentUser()),
        ]);
    }

    /** @return array<string, string> */
    private function filtersFromRequest(Request $request, ?string $stage = null): array
    {
        return [
            'q' => trim((string) $request->query->get('q', '')),
            'dateFrom' => trim((string) $request->query->get('dateFrom', '')),
            'dateTo' => trim((string) $request->query->get('dateTo', '')),
            'statut' => trim((string) $request->query->get('statut', '')),
            'usage' => trim((string) $request->query->get('usage', '')),
            'fournisseur' => trim((string) $request->query->get('fournisseur', '')),
            'especePoisson' => trim((string) $request->query->get('especePoisson', '')),
            'chambreFroide' => trim((string) $request->query->get('chambreFroide', '')),
            'sort' => trim((string) $request->query->get('sort', 'date')),
            'direction' => trim((string) $request->query->get('direction', 'desc')),
            'stage' => $stage ?? trim((string) $request->query->get('stage', '')),
        ];
    }

    /** @param class-string $formType */
    private function renderStageModal(FishReception $reception, string $formType, string $route, string $title, string $message, string $icon, string $buttonClass, float $available): Response
    {
        $this->denyAccessUnlessGranted(FishReceptionVoter::TRANSITION, $reception);
        $excelStage = $this->stageForFormType($formType);

        $parameters = [
            'item' => $reception,
            'form' => $this->buildStageForm($reception, $formType, $route, $available),
            'title' => $title,
            'message' => $message,
            'icon' => $icon,
            'button_class' => $buttonClass,
        ];

        if ($excelStage !== null) {
            $parameters += [
                'excel_stage' => $excelStage,
                'excel_template_url' => $this->generateUrl('app_fish_reception_excel_template_item', ['id' => $reception->getId(), 'stage' => $excelStage]),
                'excel_import_url' => $this->generateUrl('app_fish_reception_excel_import_item', ['id' => $reception->getId(), 'stage' => $excelStage]),
                'excel_import_token' => $this->excelImportToken($excelStage, $reception),
            ];
        }

        return $this->render('fish_reception/_stage_action_modal.html.twig', $parameters);
    }

    /**
     * @param class-string $formType
     * @param callable(float): FishReception $action
     */
    private function handleStageAction(FishReception $reception, Request $request, string $formType, string $route, float $available, callable $action, string $message): JsonResponse
    {
        $this->denyAccessUnlessGranted(FishReceptionVoter::TRANSITION, $reception);
        $form = $this->buildStageForm($reception, $formType, $route, $available);
        $form->handleRequest($request);
        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->jsonResponder->invalidForm($form);
        }

        try {
            $action($this->quantityFromForm($form));
        } catch (\DomainException $exception) {
            return $this->jsonResponder->error($exception->getMessage(), [], 422);
        }

        return $this->jsonResponder->success($message, [
            'closeModal' => true,
            'refreshRegions' => ['fishReceptionGrid', 'fishReceptionFactoryOverview', 'fishReceptionShow'],
        ]);
    }

    /** @param class-string $formType */
    private function buildStageForm(FishReception $reception, string $formType, string $route, float $available): FormInterface
    {
        $options = [
            'action' => $this->generateUrl($route, ['id' => $reception->getId()]),
            'available_quantity' => $available,
            'validation_groups' => false,
        ];

        if ($formType === FishReceptionFreezingType::class) {
            $options['factory_unit_choices'] = $this->factoryUnitService->tunnelChoices($this->currentUser(), $reception->getTunnel());
            $options['capacity_check_url'] = $this->generateUrl('app_fish_reception_freezing_capacity_check', ['id' => $reception->getId()]);
            $options['attr'] = ['data-freezing-capacity-form' => 'true'];
        } elseif ($formType === FishReceptionTreatmentType::class) {
            $options['attr'] = ['data-treatment-box-form' => 'true'];
        } elseif ($formType === FishReceptionStorageType::class) {
            $options['factory_unit_choices'] = $this->factoryUnitService->storageChoices($this->currentUser(), $reception->getChambreFroide());
            $options['capacity_check_url'] = $this->generateUrl('app_fish_reception_storage_capacity_check', ['id' => $reception->getId()]);
            $options['attr'] = ['data-factory-capacity-form' => 'true'];
        } elseif (in_array($formType, [FishReceptionPackagingType::class, FishReceptionShippingType::class], true)) {
            $options['choice_lists'] = $this->receptionService->formChoiceLists($this->currentUser());
        }

        return $this->createForm($formType, $reception, $options);
    }

    private function downloadExcelTemplate(string $stage, ?FishReception $reception): BinaryFileResponse
    {
        $path = $this->excelFormService->exportTemplate($stage, $reception, $this->currentUser(), $this->excelChoices($stage, $reception));
        $filename = 'modele-'.$stage.'-reception'.($reception instanceof FishReception ? '-'.$reception->getNumeroReception() : '').'.xlsx';
        $response = new BinaryFileResponse($path);
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $filename);
        $response->deleteFileAfterSend(true);

        return $response;
    }

    private function importExcelTemplate(string $stage, Request $request, ?FishReception $reception): JsonResponse
    {
        if (!$this->isCsrfTokenValid($this->excelImportTokenId($stage, $reception), (string) $request->request->get('token'))) {
            return $this->jsonResponder->error('Jeton de securite invalide. Rechargez la page.', [], 422);
        }

        $file = $request->files->get('file');
        if (!$file instanceof UploadedFile || !$file->isValid()) {
            return $this->jsonResponder->error('Fichier Excel invalide ou manquant.', [], 422);
        }

        try {
            $result = $this->excelFormService->importTemplate($stage, $file->getPathname(), $this->excelChoices($stage, $reception));
        } catch (\Throwable $exception) {
            return $this->jsonResponder->error('Impossible de lire ce fichier Excel. Telechargez un nouveau modele puis reessayez.', [
                'detail' => $exception->getMessage(),
            ], 422);
        }

        return $this->jsonResponder->success(
            $result['hasErrors'] ? 'Import effectue avec des erreurs a corriger.' : 'Import effectue. Verifiez puis validez le formulaire.',
            $result,
        );
    }

    /** @return array<string, list<string>> */
    private function excelChoices(string $stage, ?FishReception $reception = null): array
    {
        $choiceLists = $this->receptionService->formChoiceLists($this->currentUser());
        $choices = [];
        foreach ($choiceLists as $key => $values) {
            $choices[$key] = array_values(array_filter(array_map('strval', is_array($values) ? $values : [])));
        }

        if ($stage === 'congelation') {
            $choices['tunnel'] = array_values($this->factoryUnitService->tunnelChoices($this->currentUser(), $reception?->getTunnel()));
        }

        if ($stage === 'stockage') {
            $choices['chambreFroide'] = array_values($this->factoryUnitService->storageChoices($this->currentUser(), $reception?->getChambreFroide()));
        }

        return $choices;
    }

    private function excelImportToken(string $stage, ?FishReception $reception = null): string
    {
        return $this->csrfTokenManager->getToken($this->excelImportTokenId($stage, $reception))->getValue();
    }

    private function excelImportTokenId(string $stage, ?FishReception $reception = null): string
    {
        return 'fish_reception_excel_'.$stage.'_'.($reception?->getId() ?? 'new');
    }

    /** @param class-string $formType */
    private function stageForFormType(string $formType): ?string
    {
        return match ($formType) {
            FishReceptionTreatmentType::class => 'traitement',
            FishReceptionPackagingType::class => 'emballage',
            FishReceptionFreezingType::class => 'congelation',
            FishReceptionStorageType::class => 'stockage',
            FishReceptionShippingType::class => 'expedition',
            default => null,
        };
    }

    private function quantityFromForm(FormInterface $form): float
    {
        return (float) str_replace(',', '.', (string) $form->get('quantity')->getData());
    }

    /** @return array<string, mixed> */
    private function filterChoices(): array
    {
        $choices = $this->receptionService->filterChoices($this->currentUser());
        $chambres = $choices['chambres'] ?? [];
        foreach ($this->factoryUnitService->storageChoices($this->currentUser()) as $reference) {
            if (!in_array($reference, $chambres, true)) {
                $chambres[] = $reference;
            }
        }
        sort($chambres);
        $choices['chambres'] = $chambres;

        return $choices;
    }

    /** @return array<string, string> */
    private function stageConfig(string $stage): array
    {
        return match ($stage) {
            'traitement' => [
                'title' => 'Traitement / Production',
                'description' => 'Deduction de la reception vers la preparation avant conditionnement.',
                'source_label' => 'Quantite recue',
                'moved_label' => 'Envoyee traitement',
                'available_label' => 'Reste reception',
                'rate_label' => 'Taux traitement',
            ],
            'emballage' => [
                'title' => 'Conditionnement / Emballage',
                'description' => 'Conditionnement des quantites preparees avant congelation.',
                'source_label' => 'Quantite preparee',
                'moved_label' => 'Emballee',
                'available_label' => 'Reste traitement',
                'rate_label' => 'Taux emballage',
            ],
            'congelation' => [
                'title' => 'Congelation',
                'description' => 'Passage tunnel des produits conditionnes.',
                'source_label' => 'Quantite emballee',
                'moved_label' => 'Congelee',
                'available_label' => 'Reste emballage',
                'rate_label' => 'Taux congelation',
            ],
            'stockage' => [
                'title' => 'Stockage',
                'description' => 'Entrees en chambre froide depuis les lots congeles.',
                'source_label' => 'Quantite congelee',
                'moved_label' => 'Stockee',
                'available_label' => 'Reste congelation',
                'rate_label' => 'Taux stockage',
            ],
            'expedition' => [
                'title' => 'Expedition',
                'description' => 'Sorties client depuis le stock disponible.',
                'source_label' => 'Quantite stockee',
                'moved_label' => 'Expediee',
                'available_label' => 'Reste stock',
                'rate_label' => 'Taux expedition',
            ],
            default => [
                'title' => 'Receptions',
                'description' => 'Creation, validation et suivi des receptions matiere premiere.',
                'source_label' => 'Quantite recue',
                'moved_label' => 'Envoyee traitement',
                'available_label' => 'Disponible reception',
                'rate_label' => 'Taux traitement',
            ],
        };
    }

    private function transition(FishReception $reception, Request $request, string $csrfPrefix, string $message, callable $action): JsonResponse
    {
        $this->denyAccessUnlessGranted(FishReceptionVoter::TRANSITION, $reception);
        $payload = $request->toArray();
        try {
            $this->assertCsrf((string) ($payload['token'] ?? ''), $csrfPrefix.$reception->getId());
            $action();
        } catch (\DomainException $exception) {
            return $this->jsonResponder->error($exception->getMessage(), [], 422);
        }

        return $this->jsonResponder->success($message, [
            'refreshRegions' => ['fishReceptionGrid', 'fishReceptionFactoryOverview', 'fishReceptionShow'],
        ]);
    }

    private function assertCsrf(string $token, string $id): void
    {
        if (!$this->isCsrfTokenValid($id, $token)) {
            throw new \DomainException('Jeton de securite invalide. Rechargez la page.');
        }
    }

    private function currentUser(): User
    {
        $user = $this->getUser();
        \assert($user instanceof User);

        return $user;
    }
}
