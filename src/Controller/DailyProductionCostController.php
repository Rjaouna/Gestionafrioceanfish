<?php

namespace App\Controller;

use App\Entity\DailyProductionCost;
use App\Entity\User;
use App\Form\DailyProductionCostType;
use App\Security\Voter\ModuleAccessVoter;
use App\Service\CoutRevient\CoutRevientChargeConfigService;
use App\Service\CoutRevient\DailyProductionCostCalculatorService;
use App\Service\CoutRevient\DailyProductionCostPdfService;
use App\Service\CoutRevient\DailyProductionCostService;
use App\Service\JsonResponder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/cout-revient/journalier')]
#[IsGranted('ROLE_USER')]
final class DailyProductionCostController extends AbstractController
{
    public function __construct(
        private readonly DailyProductionCostService $service,
        private readonly DailyProductionCostCalculatorService $calculator,
        private readonly DailyProductionCostPdfService $pdfService,
        private readonly CoutRevientChargeConfigService $chargeConfigService,
        private readonly JsonResponder $jsonResponder,
    ) {
    }

    #[Route('', name: 'app_daily_production_cost_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $this->denyAccessUnlessGranted(ModuleAccessVoter::ACCESS, 'cout-revient');
        $result = $this->service->search($this->currentUser(), $this->filtersFromRequest($request), $request->query->getInt('page', 1));

        return $this->render('daily_production_cost/index.html.twig', [
            'items' => $result['items'],
            'pagination' => $result,
            'filters' => $result['filters'],
            'totals' => $result['totals'],
        ]);
    }

    #[Route('/nouveau', name: 'app_daily_production_cost_new', methods: ['GET'])]
    public function new(): Response
    {
        $this->denyAccessUnlessGranted(ModuleAccessVoter::ACCESS, 'cout-revient');
        $item = new DailyProductionCost();

        return $this->render('daily_production_cost/new.html.twig', [
            'form' => $this->buildForm($item, 'app_daily_production_cost_create'),
            'item' => $item,
            'charge_configs' => $this->chargeConfigService->forLotSelection($this->currentUser()),
            'title' => 'Nouveau cout journalier',
            'submit_label' => 'Enregistrer la journee',
        ]);
    }

    #[Route('/nouveau', name: 'app_daily_production_cost_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(ModuleAccessVoter::ACCESS, 'cout-revient');
        $item = new DailyProductionCost();
        $form = $this->buildForm($item, 'app_daily_production_cost_create');
        $form->handleRequest($request);
        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->jsonResponder->invalidForm($form);
        }

        $this->service->create($item, $this->currentUser(), $this->chargeRowsFromRequest($request));

        return $this->jsonResponder->success('Cout journalier enregistre.', [
            'redirectUrl' => $this->generateUrl('app_daily_production_cost_view', ['id' => $item->getId()]),
        ], 201);
    }

    #[Route('/{id}/voir', name: 'app_daily_production_cost_view', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function view(DailyProductionCost $item): Response
    {
        $this->denyAccessUnlessGranted(ModuleAccessVoter::ACCESS, 'cout-revient');
        $this->calculator->calculate($item);

        return $this->render('daily_production_cost/show.html.twig', [
            'item' => $item,
            'charts' => $this->calculator->chartData($item),
        ]);
    }

    #[Route('/{id}/modifier', name: 'app_daily_production_cost_edit', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function edit(DailyProductionCost $item): Response
    {
        $this->denyAccessUnlessGranted(ModuleAccessVoter::ACCESS, 'cout-revient');

        return $this->render('daily_production_cost/edit.html.twig', [
            'form' => $this->buildForm($item, 'app_daily_production_cost_update', ['id' => $item->getId()]),
            'item' => $item,
            'charge_configs' => $this->chargeConfigService->forLotSelection($this->currentUser()),
            'title' => sprintf('Modifier %s', $item->getReference()),
            'submit_label' => 'Mettre a jour',
        ]);
    }

    #[Route('/{id}/modifier', name: 'app_daily_production_cost_update', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function update(DailyProductionCost $item, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(ModuleAccessVoter::ACCESS, 'cout-revient');
        $form = $this->buildForm($item, 'app_daily_production_cost_update', ['id' => $item->getId()]);
        $form->handleRequest($request);
        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->jsonResponder->invalidForm($form);
        }

        $this->service->update($item, $this->currentUser(), $this->chargeRowsFromRequest($request));

        return $this->jsonResponder->success('Cout journalier mis a jour.', [
            'redirectUrl' => $this->generateUrl('app_daily_production_cost_view', ['id' => $item->getId()]),
        ]);
    }

    #[Route('/ajax/calculate', name: 'app_daily_production_cost_calculate', methods: ['POST'])]
    public function calculate(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(ModuleAccessVoter::ACCESS, 'cout-revient');
        $payload = $request->getContentTypeFormat() === 'json' ? $request->toArray() : $request->request->all();

        return $this->jsonResponder->success('Calcul mis a jour.', $this->calculator->calculatePayload($payload));
    }

    #[Route('/{id}/rapport.pdf', name: 'app_daily_production_cost_pdf', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function pdf(DailyProductionCost $item): Response
    {
        $this->denyAccessUnlessGranted(ModuleAccessVoter::ACCESS, 'cout-revient');
        $filename = sprintf('cout-journalier-%s.pdf', (string) $item->getReference());

        return new Response($this->pdfService->generate($item), Response::HTTP_OK, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => HeaderUtils::makeDisposition(ResponseHeaderBag::DISPOSITION_INLINE, $filename),
        ]);
    }

    /** @return FormInterface<DailyProductionCost> */
    private function buildForm(DailyProductionCost $item, string $route, array $params = []): FormInterface
    {
        return $this->createForm(DailyProductionCostType::class, $item, [
            'action' => $this->generateUrl($route, $params),
        ]);
    }

    /** @return array<string, mixed> */
    private function filtersFromRequest(Request $request): array
    {
        return [
            'q' => $request->query->get('q', ''),
            'dateFrom' => $request->query->get('dateFrom', ''),
            'dateTo' => $request->query->get('dateTo', ''),
        ];
    }

    /** @return array<int|string, mixed> */
    private function chargeRowsFromRequest(Request $request): array
    {
        $rows = $request->request->all('chargeLines');

        return is_array($rows) ? $rows : [];
    }

    private function currentUser(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        return $user;
    }
}
