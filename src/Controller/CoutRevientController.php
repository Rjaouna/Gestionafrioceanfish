<?php

namespace App\Controller;

use App\Entity\CoutRevient;
use App\Entity\User;
use App\Form\CoutRevientType;
use App\Repository\CoutRevientRepository;
use App\Security\Voter\CoutRevientVoter;
use App\Security\Voter\ModuleAccessVoter;
use App\Service\CoutRevient\CoutRevientCalculatorService;
use App\Service\CoutRevient\CoutRevientChargeConfigService;
use App\Service\CoutRevient\CoutRevientDashboardService;
use App\Service\CoutRevient\CoutRevientExcelExporterService;
use App\Service\CoutRevient\CoutRevientService;
use App\Service\JsonResponder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/cout-revient')]
#[IsGranted('ROLE_USER')]
final class CoutRevientController extends AbstractController
{
    public function __construct(
        private readonly CoutRevientService $coutRevientService,
        private readonly CoutRevientDashboardService $dashboardService,
        private readonly CoutRevientCalculatorService $calculator,
        private readonly CoutRevientChargeConfigService $chargeConfigService,
        private readonly CoutRevientExcelExporterService $excelExporter,
        private readonly CoutRevientRepository $repository,
        private readonly JsonResponder $jsonResponder,
    ) {
    }

    #[Route('', name: 'app_cout_revient_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $this->denyAccessUnlessGranted(ModuleAccessVoter::ACCESS, 'cout-revient');
        $result = $this->coutRevientService->search($this->currentUser(), $this->filtersFromRequest($request), $request->query->getInt('page', 1));

        return $this->render('cout_revient/index.html.twig', [
            'items' => $result['items'],
            'pagination' => $result,
            'filters' => $result['filters'],
            'filter_choices' => $this->coutRevientService->filterChoices($this->currentUser()),
        ]);
    }

    #[Route('/ajax/list', name: 'app_cout_revient_search', methods: ['GET'])]
    public function search(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(ModuleAccessVoter::ACCESS, 'cout-revient');
        $result = $this->coutRevientService->search($this->currentUser(), $this->filtersFromRequest($request), $request->query->getInt('page', 1));

        return $this->jsonResponder->success('Liste mise a jour.', [
            'html' => $this->renderView('cout_revient/_grid.html.twig', [
                'items' => $result['items'],
                'pagination' => $result,
            ]),
            'count' => $result['total'],
            'page' => $result['page'],
            'pages' => $result['pages'],
        ]);
    }

    #[Route('/dashboard', name: 'app_cout_revient_dashboard', methods: ['GET'])]
    public function dashboard(Request $request): Response
    {
        $this->denyAccessUnlessGranted(ModuleAccessVoter::ACCESS, 'cout-revient');
        $dashboard = $this->dashboardService->build($this->currentUser(), $this->filtersFromRequest($request));

        return $this->render('cout_revient/dashboard.html.twig', [
            'dashboard' => $dashboard,
            'filters' => $dashboard['filters'],
            'filter_choices' => $this->coutRevientService->filterChoices($this->currentUser()),
        ]);
    }

    #[Route('/ajax/dashboard-stats', name: 'app_cout_revient_dashboard_stats', methods: ['GET'])]
    public function dashboardStats(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(ModuleAccessVoter::ACCESS, 'cout-revient');
        $dashboard = $this->dashboardService->build($this->currentUser(), $this->filtersFromRequest($request));

        return $this->jsonResponder->success('Dashboard mis a jour.', [
            'html' => $this->renderView('cout_revient/_dashboard_content.html.twig', [
                'dashboard' => $dashboard,
            ]),
            'stats' => $dashboard['stats'],
        ]);
    }

    #[Route('/nouveau', name: 'app_cout_revient_new', methods: ['GET'])]
    public function new(): Response
    {
        $this->denyAccessUnlessGranted(CoutRevientVoter::CREATE);
        $coutRevient = new CoutRevient();

        return $this->render('cout_revient/new.html.twig', [
            'form' => $this->buildForm($coutRevient, 'app_cout_revient_create'),
            'item' => $coutRevient,
            'charge_configs' => $this->chargeConfigService->active($this->currentUser()),
            'title' => 'Nouveau cout de revient',
            'submit_label' => 'Enregistrer brouillon',
        ]);
    }

    #[Route('/nouveau', name: 'app_cout_revient_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(CoutRevientVoter::CREATE);
        $coutRevient = new CoutRevient();
        $form = $this->buildForm($coutRevient, 'app_cout_revient_create');
        $form->handleRequest($request);
        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->jsonResponder->invalidForm($form);
        }

        $this->coutRevientService->create($coutRevient, $this->currentUser(), $this->shouldValidate($request), $this->chargeRowsFromRequest($request));

        return $this->jsonResponder->success('Cout de revient enregistre.', [
            'redirectUrl' => $this->generateUrl('app_cout_revient_view', ['id' => $coutRevient->getId()]),
        ], 201);
    }

    #[Route('/{id}/voir', name: 'app_cout_revient_view', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function view(CoutRevient $coutRevient, Request $request): Response
    {
        $this->denyAccessUnlessGranted(CoutRevientVoter::VIEW, $coutRevient);

        if ($request->isXmlHttpRequest()) {
            return $this->render('cout_revient/_details_modal.html.twig', [
                'item' => $coutRevient,
            ]);
        }

        return $this->render('cout_revient/show.html.twig', [
            'item' => $coutRevient,
        ]);
    }

    #[Route('/{id}/modifier', name: 'app_cout_revient_edit', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function editForm(CoutRevient $coutRevient): Response
    {
        $this->denyAccessUnlessGranted(CoutRevientVoter::EDIT, $coutRevient);

        return $this->render('cout_revient/edit.html.twig', [
            'form' => $this->buildForm($coutRevient, 'app_cout_revient_update', ['id' => $coutRevient->getId()]),
            'item' => $coutRevient,
            'charge_configs' => $this->chargeConfigService->active($this->currentUser()),
            'title' => sprintf('Modifier le lot %s', $coutRevient->getNumeroLot()),
            'submit_label' => 'Enregistrer',
        ]);
    }

    #[Route('/{id}/modifier', name: 'app_cout_revient_update', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function update(CoutRevient $coutRevient, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(CoutRevientVoter::EDIT, $coutRevient);
        $form = $this->buildForm($coutRevient, 'app_cout_revient_update', ['id' => $coutRevient->getId()]);
        $form->handleRequest($request);
        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->jsonResponder->invalidForm($form);
        }

        $this->coutRevientService->update($coutRevient, $this->currentUser(), $this->shouldValidate($request), $this->chargeRowsFromRequest($request));

        return $this->jsonResponder->success('Cout de revient mis a jour.', [
            'redirectUrl' => $this->generateUrl('app_cout_revient_view', ['id' => $coutRevient->getId()]),
        ]);
    }

    #[Route('/{id}/valider', name: 'app_cout_revient_validate', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function validate(CoutRevient $coutRevient, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(CoutRevientVoter::VALIDATE, $coutRevient);
        $payload = $request->toArray();
        $this->assertCsrf((string) ($payload['token'] ?? ''), 'validate_cout_revient_'.$coutRevient->getId());
        $this->coutRevientService->validate($coutRevient, $this->currentUser());

        return $this->jsonResponder->success('Le lot a ete valide.', ['reload' => true]);
    }

    #[Route('/{id}/dupliquer', name: 'app_cout_revient_duplicate', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function duplicate(CoutRevient $coutRevient, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(CoutRevientVoter::DUPLICATE, $coutRevient);
        $payload = $request->toArray();
        $this->assertCsrf((string) ($payload['token'] ?? ''), 'duplicate_cout_revient_'.$coutRevient->getId());
        $duplicate = $this->coutRevientService->duplicate($coutRevient, $this->currentUser());

        return $this->jsonResponder->success('Le lot a ete duplique.', [
            'redirectUrl' => $this->generateUrl('app_cout_revient_edit', ['id' => $duplicate->getId()]),
        ]);
    }

    #[Route('/{id}/supprimer', name: 'app_cout_revient_delete', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    public function delete(CoutRevient $coutRevient, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(CoutRevientVoter::DELETE, $coutRevient);
        $payload = $request->toArray();
        $this->assertCsrf((string) ($payload['token'] ?? ''), 'delete_cout_revient_'.$coutRevient->getId());
        $movedToTrash = $this->coutRevientService->delete($coutRevient, $this->currentUser());

        return $this->jsonResponder->success(
            $movedToTrash ? 'Le lot a ete deplace dans la corbeille.' : 'Le lot a ete supprime.',
            ['reload' => true],
        );
    }

    #[Route('/ajax/calculate', name: 'app_cout_revient_calculate', methods: ['POST'])]
    public function calculate(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(ModuleAccessVoter::ACCESS, 'cout-revient');
        $payload = $request->getContentTypeFormat() === 'json' ? $request->toArray() : $request->request->all();

        return $this->jsonResponder->success('Calcul mis a jour.', $this->calculator->calculatePayload($payload));
    }

    #[Route('/export/excel', name: 'app_cout_revient_export_excel', methods: ['GET'])]
    public function exportExcel(Request $request): BinaryFileResponse
    {
        $this->denyAccessUnlessGranted(CoutRevientVoter::EXPORT);
        $filters = $this->coutRevientService->normalizeFilters($this->filtersFromRequest($request));
        $items = $this->coutRevientService->exportItems($this->currentUser(), $filters);
        $stats = $this->repository->getDashboardStats($filters);
        $path = $this->excelExporter->exportGlobal($items, $stats, $filters, $this->currentUser());

        return $this->download($path, 'rapport-cout-revient-'.(new \DateTimeImmutable())->format('Y-m-d').'.xlsx');
    }

    #[Route('/export/excel/{id}', name: 'app_cout_revient_export_excel_item', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function exportExcelItem(CoutRevient $coutRevient): BinaryFileResponse
    {
        $this->denyAccessUnlessGranted(CoutRevientVoter::EXPORT, $coutRevient);
        $path = $this->excelExporter->exportLot($coutRevient, $this->currentUser());

        return $this->download($path, 'fiche-cout-revient-'.$coutRevient->getNumeroLot().'.xlsx');
    }

    /** @param array<string, int|string|null> $routeParameters */
    private function buildForm(CoutRevient $coutRevient, string $route, array $routeParameters = []): FormInterface
    {
        return $this->createForm(CoutRevientType::class, $coutRevient, [
            'action' => $this->generateUrl($route, $routeParameters),
        ]);
    }

    /** @return array<string, string> */
    private function filtersFromRequest(Request $request): array
    {
        return [
            'q' => trim((string) $request->query->get('q', '')),
            'dateFrom' => trim((string) $request->query->get('dateFrom', '')),
            'dateTo' => trim((string) $request->query->get('dateTo', '')),
            'produit' => trim((string) $request->query->get('produit', '')),
            'client' => trim((string) $request->query->get('client', '')),
            'statut' => trim((string) $request->query->get('statut', '')),
            'rentabilite' => trim((string) $request->query->get('rentabilite', '')),
            'sort' => trim((string) $request->query->get('sort', 'date')),
            'direction' => trim((string) $request->query->get('direction', 'desc')),
        ];
    }

    private function shouldValidate(Request $request): bool
    {
        return (string) $request->request->get('saveMode', '') === 'validate';
    }

    /** @return array<int|string, mixed> */
    private function chargeRowsFromRequest(Request $request): array
    {
        $payload = $request->request->all();

        return is_array($payload['chargeLines'] ?? null) ? $payload['chargeLines'] : [];
    }

    private function assertCsrf(string $token, string $id): void
    {
        if (!$this->isCsrfTokenValid($id, $token)) {
            throw new \DomainException('Jeton de securite invalide. Rechargez la page.');
        }
    }

    private function download(string $path, string $filename): BinaryFileResponse
    {
        $response = new BinaryFileResponse($path);
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $filename);
        $response->deleteFileAfterSend(true);

        return $response;
    }

    private function currentUser(): User
    {
        $user = $this->getUser();
        \assert($user instanceof User);

        return $user;
    }
}
