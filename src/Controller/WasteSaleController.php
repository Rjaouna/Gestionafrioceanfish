<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\WasteSale;
use App\Form\WasteSaleType;
use App\Security\Voter\ModuleAccessVoter;
use App\Service\JsonResponder;
use App\Service\WasteSaleService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/ventes-dechets')]
#[IsGranted('ROLE_USER')]
final class WasteSaleController extends AbstractController
{
    private const MODULE_SLUG = 'ventes-dechets';

    public function __construct(
        private readonly WasteSaleService $saleService,
        private readonly JsonResponder $jsonResponder,
    ) {
    }

    #[Route('', name: 'app_waste_sale_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $this->denyAccessUnlessGranted(ModuleAccessVoter::ACCESS, self::MODULE_SLUG);
        $filters = $this->filtersFromRequest($request);
        $result = $this->saleService->search($filters, max(1, $request->query->getInt('page', 1)));

        return $this->render('waste_sale/index.html.twig', [
            'sales' => $result['items'],
            'pagination' => $result,
            'filters' => $result['filters'],
            'stats' => $this->saleService->stats($filters),
            'buyer_choices' => $this->saleService->buyerChoices(),
            'payment_methods' => WasteSale::PAYMENT_METHOD_LABELS,
            'create_form' => $this->buildForm(new WasteSale(), 'app_waste_sale_create'),
        ]);
    }

    #[Route('/creer', name: 'app_waste_sale_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(ModuleAccessVoter::ACCESS, self::MODULE_SLUG);
        $sale = new WasteSale();
        $form = $this->buildForm($sale, 'app_waste_sale_create');
        $form->handleRequest($request);
        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->jsonResponder->invalidForm($form);
        }

        $this->saleService->create($sale, $this->currentUser());

        return $this->jsonResponder->success('Vente de dechets enregistree.', ['reload' => true], 201);
    }

    #[Route('/{id}/formulaire', name: 'app_waste_sale_form', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function form(WasteSale $sale): Response
    {
        $this->denyAccessUnlessGranted(ModuleAccessVoter::ACCESS, self::MODULE_SLUG);

        return $this->render('waste_sale/_form_modal.html.twig', [
            'form' => $this->buildForm($sale, 'app_waste_sale_update', ['id' => $sale->getId()]),
            'sale' => $sale,
            'title' => sprintf('Modifier %s', $sale->getReference()),
            'submit_label' => 'Enregistrer',
            'buyer_choices' => $this->saleService->buyerChoices(),
        ]);
    }

    #[Route('/{id}/modifier', name: 'app_waste_sale_update', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function update(WasteSale $sale, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(ModuleAccessVoter::ACCESS, self::MODULE_SLUG);
        $form = $this->buildForm($sale, 'app_waste_sale_update', ['id' => $sale->getId()]);
        $form->handleRequest($request);
        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->jsonResponder->invalidForm($form);
        }

        $this->saleService->update($sale, $this->currentUser());

        return $this->jsonResponder->success('Vente de dechets modifiee.', ['reload' => true]);
    }

    #[Route('/{id}/supprimer', name: 'app_waste_sale_delete', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    public function delete(WasteSale $sale, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(ModuleAccessVoter::ACCESS, self::MODULE_SLUG);
        $payload = $request->toArray();
        if (!$this->isCsrfTokenValid('delete_waste_sale_'.$sale->getId(), (string) ($payload['token'] ?? ''))) {
            return $this->jsonResponder->error('Jeton de securite invalide. Rechargez la page.', [], 422);
        }

        $this->saleService->delete($sale, $this->currentUser());

        return $this->jsonResponder->success('Vente de dechets supprimee.', ['reload' => true]);
    }

    /** @param array<string, int|string|null> $routeParameters */
    private function buildForm(WasteSale $sale, string $route, array $routeParameters = []): FormInterface
    {
        return $this->createForm(WasteSaleType::class, $sale, [
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
            'buyerName' => trim((string) $request->query->get('buyerName', '')),
            'paymentMethod' => trim((string) $request->query->get('paymentMethod', '')),
        ];
    }

    private function currentUser(): User
    {
        $user = $this->getUser();
        \assert($user instanceof User);

        return $user;
    }
}
