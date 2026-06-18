<?php

namespace App\Controller\Maintenance;

use App\Entity\User;
use App\Service\JsonResponder;
use App\Service\Maintenance\MaintenanceShareService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/maintenance/partages/{type}/{id}', requirements: ['type' => 'intervenant|contract|intervention', 'id' => '\d+'])]
#[IsGranted('ROLE_USER')]
final class MaintenanceShareController extends AbstractController
{
    public function __construct(
        private readonly MaintenanceShareService $shareService,
        private readonly JsonResponder $jsonResponder,
    ) {
    }

    #[Route('', name: 'app_maintenance_share_modal', methods: ['GET'])]
    public function modal(string $type, int $id): Response
    {
        $item = $this->shareService->resolve($type, $id);

        return $this->render('maintenance/_share_modal.html.twig', [
            'item' => $item,
            'item_id' => $id,
            'item_type' => $type,
            'item_title' => $this->shareService->titleFor($item),
            'item_label' => $this->shareService->labelFor($item),
            'share_matrix' => $this->shareService->getShareMatrix($item, $this->currentUser()),
        ]);
    }

    #[Route('', name: 'app_maintenance_share_save', methods: ['POST'])]
    public function save(string $type, int $id, Request $request): JsonResponse
    {
        $item = $this->shareService->resolve($type, $id);
        $payload = $request->toArray();
        if (!$this->isCsrfTokenValid('share_maintenance_'.$type.'_'.$id, (string) ($payload['token'] ?? ''))) {
            throw new \DomainException('Jeton de sécurité invalide. Rechargez la page.');
        }

        $items = is_array($payload['shares'] ?? null) ? $payload['shares'] : [];
        $this->shareService->synchronize($item, $items, $this->currentUser());

        return $this->jsonResponder->success('Les partages maintenance ont été enregistrés.', ['reload' => true]);
    }

    private function currentUser(): User
    {
        $user = $this->getUser();
        \assert($user instanceof User);

        return $user;
    }
}
