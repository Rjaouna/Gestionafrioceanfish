<?php

namespace App\Controller\Inventory;

use App\Entity\InventoryMovement;
use App\Entity\User;
use App\Form\InventoryMovementType;
use App\Security\Voter\InventoryVoter;
use App\Service\Inventory\InventoryMovementService;
use App\Service\JsonResponder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/inventaire/mouvements')]
#[IsGranted('ROLE_USER')]
final class InventoryMovementController extends AbstractController
{
    public function __construct(
        private readonly InventoryMovementService $movementService,
        private readonly JsonResponder $jsonResponder,
    ) {
    }

    #[Route('', name: 'app_inventory_movement_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $this->denyAccessUnlessGranted(InventoryVoter::ACCESS);
        $filters = [
            'q' => (string) $request->query->get('q', ''),
            'type' => (string) $request->query->get('type', ''),
        ];

        return $this->render('inventory/movement/index.html.twig', [
            'movements' => $this->movementService->search($this->currentUser(), $filters),
            'filters' => $filters,
        ]);
    }

    #[Route('/recherche', name: 'app_inventory_movement_search', methods: ['GET'])]
    public function search(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(InventoryVoter::ACCESS);
        $filters = [
            'q' => (string) $request->query->get('q', ''),
            'type' => (string) $request->query->get('type', ''),
        ];

        return $this->jsonResponder->success('Mouvements actualises.', [
            'html' => $this->renderView('inventory/movement/_table.html.twig', [
                'movements' => $this->movementService->search($this->currentUser(), $filters),
            ]),
        ]);
    }

    #[Route('/nouveau', name: 'app_inventory_movement_new', methods: ['GET'])]
    public function new(): Response
    {
        $this->denyAccessUnlessGranted(InventoryVoter::ACCESS);

        return $this->render('inventory/movement/_form_modal.html.twig', [
            'form' => $this->buildForm(new InventoryMovement(), 'app_inventory_movement_create'),
            'title' => 'Nouveau mouvement',
            'submit_label' => 'Enregistrer',
            'source_movement' => null,
        ]);
    }

    #[Route('/{id}/consulter', name: 'app_inventory_movement_view', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function view(InventoryMovement $movement): Response
    {
        $item = $movement->getItem();
        $this->denyAccessUnlessGranted(InventoryVoter::ITEM_VIEW, $item);

        return $this->render('inventory/movement/_details_modal.html.twig', [
            'movement' => $movement,
        ]);
    }

    #[Route('/{id}/corriger', name: 'app_inventory_movement_correct', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function correct(InventoryMovement $movement): Response
    {
        $item = $movement->getItem();
        $this->denyAccessUnlessGranted(InventoryVoter::ITEM_EDIT, $item);

        return $this->render('inventory/movement/_form_modal.html.twig', [
            'form' => $this->buildForm(
                $this->newCorrection($movement),
                'app_inventory_movement_correct_create',
                ['id' => $movement->getId()],
                ['item_locked' => true],
            ),
            'title' => sprintf('Corriger le mouvement #%d', $movement->getId()),
            'submit_label' => 'Enregistrer la correction',
            'source_movement' => $movement,
        ]);
    }

    #[Route('/{id}/corriger', name: 'app_inventory_movement_correct_create', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function createCorrection(InventoryMovement $movement, Request $request): JsonResponse
    {
        $item = $movement->getItem();
        $this->denyAccessUnlessGranted(InventoryVoter::ITEM_EDIT, $item);

        $correction = $this->newCorrection($movement);
        $form = $this->buildForm(
            $correction,
            'app_inventory_movement_correct_create',
            ['id' => $movement->getId()],
            ['item_locked' => true],
        );
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->jsonResponder->invalidForm($form);
        }

        try {
            $this->movementService->create($correction, $this->currentUser());
        } catch (\DomainException $exception) {
            return $this->jsonResponder->error($exception->getMessage(), [], 422);
        }

        return $this->jsonResponder->success('Le mouvement de correction a ete enregistre.', [
            'closeModal' => true,
            'refreshRegion' => 'inventory-movements',
        ], 201);
    }

    #[Route('/creer', name: 'app_inventory_movement_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(InventoryVoter::ACCESS);
        $movement = new InventoryMovement();
        $form = $this->buildForm($movement, 'app_inventory_movement_create');
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->jsonResponder->invalidForm($form);
        }

        try {
            $this->movementService->create($movement, $this->currentUser());
        } catch (\DomainException $exception) {
            return $this->jsonResponder->error($exception->getMessage(), [], 422);
        }

        return $this->jsonResponder->success('Le mouvement a ete enregistre.', [
            'closeModal' => true,
            'refreshRegion' => 'inventory-movements',
        ], 201);
    }

    /** @param array<string, int|string|null> $parameters */
    private function buildForm(InventoryMovement $movement, string $route, array $parameters = [], array $options = []): \Symfony\Component\Form\FormInterface
    {
        return $this->createForm(InventoryMovementType::class, $movement, [
            'action' => $this->generateUrl($route, $parameters),
            ...$options,
        ]);
    }

    private function suggestedCorrectionType(InventoryMovement $movement): string
    {
        return match ($movement->getMovementType()) {
            'entry' => 'retirement',
            'retirement' => 'entry',
            'assignment', 'maintenance' => 'return',
            'return' => 'assignment',
            'transfer' => 'transfer',
            default => 'adjustment',
        };
    }

    private function newCorrection(InventoryMovement $movement): InventoryMovement
    {
        $correction = (new InventoryMovement())
            ->setItem($movement->getItem())
            ->setMovementType($this->suggestedCorrectionType($movement))
            ->setQuantity($movement->getMovementType() === 'adjustment'
                ? (int) $movement->getItem()?->getQuantity()
                : $movement->getQuantity())
            ->setReason(sprintf('Correction du mouvement #%d', $movement->getId()))
            ->setNotes(sprintf(
                'Mouvement d origine: %s du %s.',
                $movement->getTypeLabel(),
                $movement->getMovementDate()->format('d/m/Y H:i'),
            ));

        if ($movement->getMovementType() === 'transfer') {
            $correction
                ->setToSite($movement->getFromSite())
                ->setToLocation($movement->getFromLocation());
        }
        if ($movement->getMovementType() === 'return') {
            $correction->setResponsibleUser($movement->getResponsibleUser());
        }

        return $correction;
    }

    private function currentUser(): User
    {
        $user = $this->getUser();
        \assert($user instanceof User);

        return $user;
    }
}
