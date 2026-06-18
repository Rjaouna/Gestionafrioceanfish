<?php

namespace App\Controller\Inventory;

use App\Entity\InventoryCartonStock;
use App\Entity\InventoryCartonStockLine;
use App\Entity\User;
use App\Form\InventoryCartonStockLineType;
use App\Form\InventoryCartonStockType;
use App\Repository\InventoryCartonStockRepository;
use App\Security\Voter\InventoryVoter;
use App\Service\Inventory\InventoryCartonStockService;
use App\Service\JsonResponder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/inventaire/stocks-cartons')]
#[IsGranted('ROLE_USER')]
final class InventoryCartonStockController extends AbstractController
{
    private const STOCK_QUICK_FIELDS = [
        'name' => 'Nom du stock',
        'sourceSheet' => 'Feuille source',
        'description' => 'Description',
        'isActive' => 'Statut actif',
    ];

    private const LINE_QUICK_FIELDS = [
        'groupName' => 'Groupe / client',
        'reference' => 'Référence',
        'lineType' => 'Type de ligne',
        'quantity' => 'Quantité',
        'unitPrice' => 'Prix unitaire',
        'totalAmount' => 'Total',
        'notes' => 'Notes',
    ];

    public function __construct(
        private readonly InventoryCartonStockRepository $stockRepository,
        private readonly InventoryCartonStockService $stockService,
        private readonly JsonResponder $jsonResponder,
    ) {
    }

    #[Route('', name: 'app_inventory_carton_stock_index', methods: ['GET'])]
    public function index(): Response
    {
        $this->denyAccessUnlessGranted(InventoryVoter::ACCESS);

        return $this->render('inventory/carton_stock/index.html.twig', [
            'stocks' => $this->stockRepository->listWithLines(),
            'summaries' => $this->stockRepository->summariesByStock(),
        ]);
    }

    #[Route('/contenu', name: 'app_inventory_carton_stock_content', methods: ['GET'])]
    public function content(): JsonResponse
    {
        $this->denyAccessUnlessGranted(InventoryVoter::ACCESS);

        return $this->jsonResponder->success('Stocks cartons actualises.', [
            'html' => $this->renderView('inventory/carton_stock/_content.html.twig', [
                'stocks' => $this->stockRepository->listWithLines(),
                'summaries' => $this->stockRepository->summariesByStock(),
            ]),
        ]);
    }

    #[Route('/stocks/nouveau', name: 'app_inventory_carton_stock_new', methods: ['GET'])]
    public function newStock(): Response
    {
        $this->denyAccessUnlessGranted(InventoryVoter::ITEM_CREATE);

        return $this->render('inventory/carton_stock/_stock_form_modal.html.twig', [
            'form' => $this->buildStockForm(new InventoryCartonStock(), 'app_inventory_carton_stock_create'),
            'title' => 'Nouveau stock carton',
            'submit_label' => 'Créer',
            'stock' => null,
        ]);
    }

    #[Route('/stocks/creer', name: 'app_inventory_carton_stock_create', methods: ['POST'])]
    public function createStock(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(InventoryVoter::ITEM_CREATE);
        $stock = new InventoryCartonStock();
        $form = $this->buildStockForm($stock, 'app_inventory_carton_stock_create');
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->jsonResponder->invalidForm($form);
        }

        $this->stockService->createStock($stock, $this->currentUser());

        return $this->jsonResponder->success('Le stock carton a été créé.', [
            'closeModal' => true,
            'refreshRegion' => 'carton-stocks',
        ], 201);
    }

    #[Route('/stocks/{id}/formulaire', name: 'app_inventory_carton_stock_form', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function stockForm(InventoryCartonStock $stock): Response
    {
        $this->denyAccessUnlessGranted(InventoryVoter::ITEM_CREATE);

        return $this->render('inventory/carton_stock/_stock_form_modal.html.twig', [
            'form' => $this->buildStockForm($stock, 'app_inventory_carton_stock_edit', ['id' => $stock->getId()]),
            'title' => 'Modifier le stock carton',
            'submit_label' => 'Enregistrer',
            'stock' => $stock,
        ]);
    }

    #[Route('/stocks/{id}/modification-rapide/{field}', name: 'app_inventory_carton_stock_quick_form', requirements: ['id' => '\d+', 'field' => 'name|sourceSheet|description|isActive'], methods: ['GET'])]
    public function stockQuickForm(InventoryCartonStock $stock, string $field): Response
    {
        $this->denyAccessUnlessGranted(InventoryVoter::ITEM_CREATE);

        return $this->render('inventory/carton_stock/_stock_quick_edit_modal.html.twig', [
            'stock' => $stock,
            'field' => $field,
            'field_label' => self::STOCK_QUICK_FIELDS[$field] ?? $field,
        ]);
    }

    #[Route('/stocks/{id}/modification-rapide/{field}', name: 'app_inventory_carton_stock_quick_update', requirements: ['id' => '\d+', 'field' => 'name|sourceSheet|description|isActive'], methods: ['POST'])]
    public function stockQuickUpdate(InventoryCartonStock $stock, string $field, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(InventoryVoter::ITEM_CREATE);
        $this->assertCsrf((string) $request->request->get('token'), 'quick_inventory_carton_stock_'.$stock->getId());

        try {
            $this->stockService->updateStockField(
                $stock,
                $field,
                $field === 'isActive' ? (bool) $request->request->get('value', false) : $request->request->get('value'),
                $this->currentUser(),
            );
        } catch (\DomainException $exception) {
            return $this->jsonResponder->error($exception->getMessage(), [], 422);
        }

        return $this->jsonResponder->success('Le champ stock carton a été modifié.', [
            'closeModal' => true,
            'refreshRegion' => 'carton-stocks',
        ]);
    }

    #[Route('/stocks/{id}/modifier', name: 'app_inventory_carton_stock_edit', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function editStock(InventoryCartonStock $stock, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(InventoryVoter::ITEM_CREATE);
        $form = $this->buildStockForm($stock, 'app_inventory_carton_stock_edit', ['id' => $stock->getId()]);
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->jsonResponder->invalidForm($form);
        }

        $this->stockService->updateStock($stock, $this->currentUser());

        return $this->jsonResponder->success('Le stock carton a été modifié.', [
            'closeModal' => true,
            'refreshRegion' => 'carton-stocks',
        ]);
    }

    #[Route('/stocks/{id}', name: 'app_inventory_carton_stock_delete', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    public function deleteStock(InventoryCartonStock $stock, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(InventoryVoter::ITEM_CREATE);
        $payload = $request->toArray();
        $this->assertCsrf((string) ($payload['token'] ?? ''), 'delete_inventory_carton_stock_'.$stock->getId());
        $this->stockService->deleteStock($stock, $this->currentUser());

        return $this->jsonResponder->success('Le stock carton a été supprimé.', [
            'refreshRegion' => 'carton-stocks',
        ]);
    }

    #[Route('/lignes/nouveau', name: 'app_inventory_carton_line_new', methods: ['GET'])]
    public function lineNew(Request $request): Response
    {
        $this->denyAccessUnlessGranted(InventoryVoter::ITEM_CREATE);
        $line = new InventoryCartonStockLine();
        $stockId = (int) $request->query->get('stock', 0);
        if ($stockId > 0) {
            $stock = $this->stockRepository->find($stockId);
            if ($stock instanceof InventoryCartonStock) {
                $line->setStock($stock);
            }
        }

        return $this->render('inventory/carton_stock/_line_form_modal.html.twig', [
            'form' => $this->buildLineForm($line, 'app_inventory_carton_line_create'),
            'title' => 'Ajouter une ligne de stock',
            'submit_label' => 'Créer',
            'line' => null,
        ]);
    }

    #[Route('/lignes/creer', name: 'app_inventory_carton_line_create', methods: ['POST'])]
    public function createLine(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(InventoryVoter::ITEM_CREATE);
        $line = new InventoryCartonStockLine();
        $form = $this->buildLineForm($line, 'app_inventory_carton_line_create');
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->jsonResponder->invalidForm($form);
        }

        try {
            $this->stockService->createLine($line, $this->currentUser());
        } catch (\DomainException $exception) {
            return $this->jsonResponder->error($exception->getMessage(), [], 422);
        }

        return $this->jsonResponder->success('La ligne de stock a été créée.', [
            'closeModal' => true,
            'refreshRegion' => 'carton-stocks',
        ], 201);
    }

    #[Route('/lignes/{id}/formulaire', name: 'app_inventory_carton_line_form', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function lineForm(InventoryCartonStockLine $line): Response
    {
        $this->denyAccessUnlessGranted(InventoryVoter::ITEM_CREATE);

        return $this->render('inventory/carton_stock/_line_form_modal.html.twig', [
            'form' => $this->buildLineForm($line, 'app_inventory_carton_line_edit', ['id' => $line->getId()]),
            'title' => 'Modifier la ligne de stock',
            'submit_label' => 'Enregistrer',
            'line' => $line,
        ]);
    }

    #[Route('/lignes/{id}/modification-rapide/{field}', name: 'app_inventory_carton_line_quick_form', requirements: ['id' => '\d+', 'field' => 'groupName|reference|lineType|quantity|unitPrice|totalAmount|notes'], methods: ['GET'])]
    public function lineQuickForm(InventoryCartonStockLine $line, string $field): Response
    {
        $this->denyAccessUnlessGranted(InventoryVoter::ITEM_CREATE);

        return $this->render('inventory/carton_stock/_line_quick_edit_modal.html.twig', [
            'line' => $line,
            'field' => $field,
            'field_label' => self::LINE_QUICK_FIELDS[$field] ?? $field,
        ]);
    }

    #[Route('/lignes/{id}/modification-rapide/{field}', name: 'app_inventory_carton_line_quick_update', requirements: ['id' => '\d+', 'field' => 'groupName|reference|lineType|quantity|unitPrice|totalAmount|notes'], methods: ['POST'])]
    public function lineQuickUpdate(InventoryCartonStockLine $line, string $field, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(InventoryVoter::ITEM_CREATE);
        $this->assertCsrf((string) $request->request->get('token'), 'quick_inventory_carton_line_'.$line->getId());

        try {
            $this->stockService->updateLineField($line, $field, $request->request->get('value'), $this->currentUser());
        } catch (\DomainException $exception) {
            return $this->jsonResponder->error($exception->getMessage(), [], 422);
        }

        return $this->jsonResponder->success('Le champ de la ligne carton a été modifié.', [
            'closeModal' => true,
            'refreshRegion' => 'carton-stocks',
        ]);
    }

    #[Route('/lignes/{id}/modifier', name: 'app_inventory_carton_line_edit', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function editLine(InventoryCartonStockLine $line, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(InventoryVoter::ITEM_CREATE);
        $form = $this->buildLineForm($line, 'app_inventory_carton_line_edit', ['id' => $line->getId()]);
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->jsonResponder->invalidForm($form);
        }

        try {
            $this->stockService->updateLine($line, $this->currentUser());
        } catch (\DomainException $exception) {
            return $this->jsonResponder->error($exception->getMessage(), [], 422);
        }

        return $this->jsonResponder->success('La ligne de stock a été modifiée.', [
            'closeModal' => true,
            'refreshRegion' => 'carton-stocks',
        ]);
    }

    #[Route('/lignes/{id}', name: 'app_inventory_carton_line_delete', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    public function deleteLine(InventoryCartonStockLine $line, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(InventoryVoter::ITEM_CREATE);
        $payload = $request->toArray();
        $this->assertCsrf((string) ($payload['token'] ?? ''), 'delete_inventory_carton_line_'.$line->getId());
        $this->stockService->deleteLine($line, $this->currentUser());

        return $this->jsonResponder->success('La ligne de stock a été supprimée.', [
            'refreshRegion' => 'carton-stocks',
        ]);
    }

    /** @param array<string, int|string|null> $parameters */
    private function buildStockForm(InventoryCartonStock $stock, string $route, array $parameters = []): \Symfony\Component\Form\FormInterface
    {
        return $this->createForm(InventoryCartonStockType::class, $stock, [
            'action' => $this->generateUrl($route, $parameters),
        ]);
    }

    /** @param array<string, int|string|null> $parameters */
    private function buildLineForm(InventoryCartonStockLine $line, string $route, array $parameters = []): \Symfony\Component\Form\FormInterface
    {
        return $this->createForm(InventoryCartonStockLineType::class, $line, [
            'action' => $this->generateUrl($route, $parameters),
        ]);
    }

    private function assertCsrf(string $token, string $id): void
    {
        if (!$this->isCsrfTokenValid($id, $token)) {
            throw new \DomainException('Jeton de sécurité invalide. Rechargez la page.');
        }
    }

    private function currentUser(): User
    {
        $user = $this->getUser();
        \assert($user instanceof User);

        return $user;
    }
}
