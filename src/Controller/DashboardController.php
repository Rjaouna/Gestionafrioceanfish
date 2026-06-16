<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\DashboardStatsService;
use App\Service\ModuleAccessService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
final class DashboardController extends AbstractController
{
    #[Route('/', name: 'app_dashboard', methods: ['GET'])]
    public function index(ModuleAccessService $moduleAccessService, DashboardStatsService $dashboardStatsService): Response
    {
        $user = $this->getUser();
        \assert($user instanceof User);

        $modules = $moduleAccessService->getAccessibleModules($user);

        return $this->render('dashboard/index.html.twig', [
            'modules' => $modules,
            'dashboard' => $dashboardStatsService->buildDashboard($user, $modules),
        ]);
    }
}
