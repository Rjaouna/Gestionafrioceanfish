<?php

namespace App\Controller\Inventory;

use App\Entity\User;
use App\Security\Voter\InventoryVoter;
use App\Service\Inventory\InventoryDashboardService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/inventaire')]
#[IsGranted('ROLE_USER')]
final class InventoryDashboardController extends AbstractController
{
    public function __construct(private readonly InventoryDashboardService $dashboardService)
    {
    }

    #[Route('', name: 'app_inventory_dashboard', methods: ['GET'])]
    public function index(): Response
    {
        $this->denyAccessUnlessGranted(InventoryVoter::ACCESS);

        return $this->render('inventory/dashboard/index.html.twig', [
            'dashboard' => $this->dashboardService->build($this->currentUser()),
        ]);
    }

    private function currentUser(): User
    {
        $user = $this->getUser();
        \assert($user instanceof User);

        return $user;
    }
}
