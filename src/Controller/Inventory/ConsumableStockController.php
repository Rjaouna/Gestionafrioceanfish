<?php

namespace App\Controller\Inventory;

use App\Entity\ConsumableStockItem;
use App\Entity\ConsumableStockMovement;
use App\Entity\User;
use App\Form\ConsumableStockEntryType;
use App\Form\ConsumableStockExitType;
use App\Form\ConsumableStockInventoryType;
use App\Form\ConsumableStockItemType;
use App\Security\Voter\InventoryVoter;
use App\Service\Inventory\ConsumableStockService;
use App\Service\JsonResponder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/inventaire/consommables')]
#[IsGranted('ROLE_USER')]
final class ConsumableStockController extends AbstractController
{
    private const DELETE_CONFIRMATION_PASSWORD = 'password';

    public function __construct(
        private readonly ConsumableStockService $stockService,
        private readonly JsonResponder $jsonResponder,
    ) {
    }

    #[Route('', name: 'app_inventory_consumable_stock_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $this->denyAccessUnlessGranted(InventoryVoter::ACCESS);
        $filters = $this->filtersFromRequest($request);

        return $this->render('inventory/consumable_stock/index.html.twig', [
            'items' => $this->stockService->search($this->currentUser(), $filters),
            'stats' => $this->stockService->dashboard($this->currentUser()),
            'filters' => $filters,
            'filter_choices' => $this->stockService->filterChoices($this->currentUser()),
        ]);
    }

    #[Route('/recherche', name: 'app_inventory_consumable_stock_search', methods: ['GET'])]
    public function search(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(InventoryVoter::ACCESS);

        return $this->jsonResponder->success('Stock actualise.', [
            'html' => $this->renderView('inventory/consumable_stock/_table.html.twig', [
                'items' => $this->stockService->search($this->currentUser(), $this->filtersFromRequest($request)),
            ]),
        ]);
    }

    #[Route('/nouveau', name: 'app_inventory_consumable_stock_new', methods: ['GET'])]
    public function new(): Response
    {
        $this->denyAccessUnlessGranted(InventoryVoter::ACCESS);

        return $this->render('inventory/consumable_stock/_item_form_modal.html.twig', [
            'form' => $this->buildItemForm(new ConsumableStockItem(), 'app_inventory_consumable_stock_create', [], true, false),
            'title' => 'Nouveau produit consommable',
            'submit_label' => 'Creer',
        ]);
    }

    #[Route('/creer', name: 'app_inventory_consumable_stock_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(InventoryVoter::ACCESS);
        $item = new ConsumableStockItem();
        $form = $this->buildItemForm($item, 'app_inventory_consumable_stock_create', [], true, false);
        $form->handleRequest($request);
        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->jsonResponder->invalidForm($form);
        }

        try {
            $this->stockService->create($item, $this->floatFromForm($form, 'initialQuantity'), $this->currentUser());
        } catch (\DomainException $exception) {
            return $this->jsonResponder->error($exception->getMessage(), [], 422);
        }

        return $this->jsonResponder->success('Produit de stock cree.', ['reload' => true], 201);
    }

    #[Route('/{id}/consulter', name: 'app_inventory_consumable_stock_view', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function view(ConsumableStockItem $item): Response
    {
        $this->denyAccessUnlessGranted(InventoryVoter::ACCESS);

        return $this->render('inventory/consumable_stock/_details_modal.html.twig', [
            'item' => $item,
            'movements' => $this->stockService->movementsForItem($item, $this->currentUser()),
        ]);
    }

    #[Route('/{id}/formulaire', name: 'app_inventory_consumable_stock_form', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function form(ConsumableStockItem $item): Response
    {
        $this->denyAccessUnlessGranted(InventoryVoter::ACCESS);

        return $this->render('inventory/consumable_stock/_item_form_modal.html.twig', [
            'form' => $this->buildItemForm($item, 'app_inventory_consumable_stock_edit', ['id' => $item->getId()]),
            'title' => sprintf('Modifier %s', $item->getName()),
            'submit_label' => 'Enregistrer',
        ]);
    }

    #[Route('/{id}/modifier', name: 'app_inventory_consumable_stock_edit', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function edit(ConsumableStockItem $item, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(InventoryVoter::ACCESS);
        $form = $this->buildItemForm($item, 'app_inventory_consumable_stock_edit', ['id' => $item->getId()]);
        $form->handleRequest($request);
        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->jsonResponder->invalidForm($form);
        }

        $this->stockService->update($item, $this->currentUser());

        return $this->jsonResponder->success('Produit de stock modifie.', ['reload' => true]);
    }

    #[Route('/{id}/entree/formulaire', name: 'app_inventory_consumable_stock_entry_form', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function entryForm(ConsumableStockItem $item): Response
    {
        $this->denyAccessUnlessGranted(InventoryVoter::ACCESS);

        return $this->render('inventory/consumable_stock/_entry_modal.html.twig', [
            'item' => $item,
            'form' => $this->createForm(ConsumableStockEntryType::class, null, [
                'action' => $this->generateUrl('app_inventory_consumable_stock_entry', ['id' => $item->getId()]),
                'choice_lists' => $this->stockService->formChoiceLists($this->currentUser()),
            ]),
        ]);
    }

    #[Route('/{id}/entree', name: 'app_inventory_consumable_stock_entry', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function entry(ConsumableStockItem $item, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(InventoryVoter::ACCESS);
        $form = $this->createForm(ConsumableStockEntryType::class, null, [
            'action' => $this->generateUrl('app_inventory_consumable_stock_entry', ['id' => $item->getId()]),
            'choice_lists' => $this->stockService->formChoiceLists($this->currentUser()),
        ]);
        $form->handleRequest($request);
        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->jsonResponder->invalidForm($form);
        }

        try {
            $this->stockService->receive($item, $this->floatFromForm($form, 'quantity'), $this->dateFromForm($form, 'movementDate'), (string) $form->get('supplier')->getData(), $form->get('unitCost')->getData(), (string) $form->get('reason')->getData(), $this->currentUser());
        } catch (\DomainException $exception) {
            return $this->jsonResponder->error($exception->getMessage(), [], 422);
        }

        return $this->jsonResponder->success('Entree de stock enregistree.', ['reload' => true]);
    }

    #[Route('/{id}/sortie/formulaire', name: 'app_inventory_consumable_stock_exit_form', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function exitForm(ConsumableStockItem $item): Response
    {
        $this->denyAccessUnlessGranted(InventoryVoter::ACCESS);

        return $this->render('inventory/consumable_stock/_exit_modal.html.twig', [
            'item' => $item,
            'form' => $this->createForm(ConsumableStockExitType::class, null, [
                'action' => $this->generateUrl('app_inventory_consumable_stock_exit', ['id' => $item->getId()]),
                'choice_lists' => $this->stockService->formChoiceLists($this->currentUser()),
            ]),
        ]);
    }

    #[Route('/{id}/sortie', name: 'app_inventory_consumable_stock_exit', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function exit(ConsumableStockItem $item, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(InventoryVoter::ACCESS);
        $form = $this->createForm(ConsumableStockExitType::class, null, [
            'action' => $this->generateUrl('app_inventory_consumable_stock_exit', ['id' => $item->getId()]),
            'choice_lists' => $this->stockService->formChoiceLists($this->currentUser()),
        ]);
        $form->handleRequest($request);
        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->jsonResponder->invalidForm($form);
        }

        try {
            $this->stockService->consume($item, $this->floatFromForm($form, 'quantity'), $this->dateFromForm($form, 'movementDate'), (string) $form->get('recipient')->getData(), (string) $form->get('reason')->getData(), $this->currentUser());
        } catch (\DomainException $exception) {
            return $this->jsonResponder->error($exception->getMessage(), [], 422);
        }

        return $this->jsonResponder->success('Sortie de stock enregistree.', ['reload' => true]);
    }

    #[Route('/{id}/inventaire/formulaire', name: 'app_inventory_consumable_stock_inventory_form', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function inventoryForm(ConsumableStockItem $item): Response
    {
        $this->denyAccessUnlessGranted(InventoryVoter::ACCESS);

        return $this->render('inventory/consumable_stock/_inventory_modal.html.twig', [
            'item' => $item,
            'form' => $this->createForm(ConsumableStockInventoryType::class, null, [
                'action' => $this->generateUrl('app_inventory_consumable_stock_inventory', ['id' => $item->getId()]),
            ]),
        ]);
    }

    #[Route('/{id}/inventaire', name: 'app_inventory_consumable_stock_inventory', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function inventory(ConsumableStockItem $item, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(InventoryVoter::ACCESS);
        $form = $this->createForm(ConsumableStockInventoryType::class, null, [
            'action' => $this->generateUrl('app_inventory_consumable_stock_inventory', ['id' => $item->getId()]),
        ]);
        $form->handleRequest($request);
        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->jsonResponder->invalidForm($form);
        }

        try {
            $this->stockService->countInventory($item, $this->floatFromForm($form, 'countedQuantity'), $this->dateFromForm($form, 'movementDate'), (string) $form->get('reason')->getData(), $this->currentUser());
        } catch (\DomainException $exception) {
            return $this->jsonResponder->error($exception->getMessage(), [], 422);
        }

        return $this->jsonResponder->success('Inventaire enregistre.', ['reload' => true]);
    }

    #[Route('/{id}/suppression', name: 'app_inventory_consumable_stock_delete_form', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function deleteForm(ConsumableStockItem $item): Response
    {
        $this->denyAccessUnlessGranted(InventoryVoter::ACCESS);

        return $this->render('inventory/consumable_stock/_delete_item_modal.html.twig', [
            'item' => $item,
        ]);
    }

    #[Route('/{id}/supprimer', name: 'app_inventory_consumable_stock_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(ConsumableStockItem $item, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(InventoryVoter::ACCESS);

        try {
            $this->assertCsrf((string) $request->request->get('token'), 'delete_consumable_stock_item_'.$item->getId());
            $this->assertDeletionPassword((string) $request->request->get('confirmationPassword'));
            $this->stockService->deleteItem($item, $this->currentUser());
        } catch (\DomainException $exception) {
            return $this->jsonResponder->error($exception->getMessage(), [], 422);
        }

        return $this->jsonResponder->success('Article supprime avec son historique.', ['reload' => true]);
    }

    #[Route('/mouvements/{id}/suppression', name: 'app_inventory_consumable_stock_movement_delete_form', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function deleteMovementForm(ConsumableStockMovement $movement): Response
    {
        $this->denyAccessUnlessGranted(InventoryVoter::ACCESS);

        return $this->render('inventory/consumable_stock/_delete_movement_modal.html.twig', [
            'movement' => $movement,
            'item' => $movement->getItem(),
        ]);
    }

    #[Route('/mouvements/{id}/supprimer', name: 'app_inventory_consumable_stock_movement_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function deleteMovement(ConsumableStockMovement $movement, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(InventoryVoter::ACCESS);

        try {
            $this->assertCsrf((string) $request->request->get('token'), 'delete_consumable_stock_movement_'.$movement->getId());
            $this->assertDeletionPassword((string) $request->request->get('confirmationPassword'));
            $this->stockService->deleteMovement($movement, $this->currentUser());
        } catch (\DomainException $exception) {
            return $this->jsonResponder->error($exception->getMessage(), [], 422);
        }

        return $this->jsonResponder->success('Mouvement supprime. Le stock a ete recalcule.', ['reload' => true]);
    }

    /** @param array<string, int|string|null> $parameters */
    private function buildItemForm(ConsumableStockItem $item, string $route, array $parameters = [], bool $includeInitialQuantity = false, bool $showReference = true): FormInterface
    {
        return $this->createForm(ConsumableStockItemType::class, $item, [
            'action' => $this->generateUrl($route, $parameters),
            'include_initial_quantity' => $includeInitialQuantity,
            'show_reference' => $showReference,
            'choice_lists' => $this->stockService->formChoiceLists($this->currentUser()),
        ]);
    }

    /** @return array<string, string> */
    private function filtersFromRequest(Request $request): array
    {
        return [
            'q' => trim((string) $request->query->get('q', '')),
            'category' => trim((string) $request->query->get('category', '')),
            'status' => trim((string) $request->query->get('status', '')),
            'active' => trim((string) $request->query->get('active', 'active')),
        ];
    }

    private function floatFromForm(FormInterface $form, string $field): float
    {
        return (float) str_replace(',', '.', (string) $form->get($field)->getData());
    }

    private function dateFromForm(FormInterface $form, string $field): \DateTimeImmutable
    {
        $value = $form->get($field)->getData();
        if ($value instanceof \DateTimeImmutable) {
            return $value;
        }

        if ($value instanceof \DateTimeInterface) {
            return \DateTimeImmutable::createFromInterface($value);
        }

        throw new \DomainException('Date invalide.');
    }

    private function assertCsrf(string $token, string $id): void
    {
        if (!$this->isCsrfTokenValid($id, $token)) {
            throw new \DomainException('Jeton de securite invalide. Rechargez la page.');
        }
    }

    private function assertDeletionPassword(string $password): void
    {
        if (!hash_equals(self::DELETE_CONFIRMATION_PASSWORD, trim($password))) {
            throw new \DomainException('Mot de passe de confirmation incorrect.');
        }
    }

    private function currentUser(): User
    {
        $user = $this->getUser();
        \assert($user instanceof User);

        return $user;
    }
}
