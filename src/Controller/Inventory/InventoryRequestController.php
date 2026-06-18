<?php

namespace App\Controller\Inventory;

use App\Entity\InventoryRequest;
use App\Entity\User;
use App\Security\Voter\InventoryVoter;
use App\Service\Inventory\InventoryRequestService;
use App\Service\JsonResponder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/inventaire/demandes')]
#[IsGranted('ROLE_USER')]
final class InventoryRequestController extends AbstractController
{
    public function __construct(
        private readonly InventoryRequestService $requestService,
        private readonly JsonResponder $jsonResponder,
    ) {
    }

    #[Route('', name: 'app_inventory_request_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $this->denyAccessUnlessGranted(InventoryVoter::ACCESS);
        $filters = $this->filters($request);

        return $this->render('inventory/request/index.html.twig', [
            'requests' => $this->requestService->search($this->currentUser(), $filters),
            'filters' => $filters,
        ]);
    }

    #[Route('/recherche', name: 'app_inventory_request_search', methods: ['GET'])]
    public function search(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(InventoryVoter::ACCESS);
        $filters = $this->filters($request);
        $requests = $this->requestService->search($this->currentUser(), $filters);

        return $this->jsonResponder->success('Demandes actualisees.', [
            'html' => $this->renderView('inventory/request/_table.html.twig', ['requests' => $requests]),
            'count' => count($requests),
        ]);
    }

    #[Route('/{id}/valider', name: 'app_inventory_request_validate_form', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function validateForm(InventoryRequest $inventoryRequest): Response
    {
        $this->denyAccessUnlessGranted(InventoryVoter::ACCESS);
        $this->denyAccessUnlessGranted(InventoryVoter::ITEM_VIEW, $inventoryRequest->getItem());

        return $this->render('inventory/request/_validate_modal.html.twig', [
            'request' => $inventoryRequest,
        ]);
    }

    #[Route('/{id}/valider', name: 'app_inventory_request_validate', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function validateRequest(InventoryRequest $inventoryRequest, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(InventoryVoter::ACCESS);
        $this->assertCsrf((string) $request->request->get('token'), 'validate_inventory_request_'.$inventoryRequest->getId());

        try {
            $countedQuantity = $inventoryRequest->isInventory()
                ? (int) $request->request->get('countedQuantity', $inventoryRequest->getItem()?->getQuantity() ?? 0)
                : null;

            $this->requestService->validate(
                $inventoryRequest,
                $this->currentUser(),
                $countedQuantity,
                (string) $request->request->get('resolutionNote', ''),
            );
        } catch (\DomainException $exception) {
            return $this->jsonResponder->error($exception->getMessage(), [], 422);
        }

        return $this->jsonResponder->success('La demande a ete validee.', [
            'closeModal' => true,
            'refreshRegion' => 'inventory-requests',
        ]);
    }

    #[Route('/{id}/annuler', name: 'app_inventory_request_cancel', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function cancel(InventoryRequest $inventoryRequest, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(InventoryVoter::ACCESS);
        $payload = $request->toArray();
        $this->assertCsrf((string) ($payload['token'] ?? ''), 'cancel_inventory_request_'.$inventoryRequest->getId());

        try {
            $this->requestService->cancel($inventoryRequest, $this->currentUser(), (string) ($payload['reason'] ?? ''));
        } catch (\DomainException $exception) {
            return $this->jsonResponder->error($exception->getMessage(), [], 422);
        }

        return $this->jsonResponder->success('La demande a ete annulee sans modifier le materiel.', [
            'refreshRegion' => 'inventory-requests',
        ]);
    }

    /** @return array<string, string> */
    private function filters(Request $request): array
    {
        return [
            'q' => (string) $request->query->get('q', ''),
            'type' => (string) $request->query->get('type', ''),
            'status' => (string) $request->query->get('status', 'pending'),
        ];
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
