<?php

namespace App\Controller;

use App\Entity\User;
use App\Security\Voter\ModuleAccessVoter;
use App\Service\DashboardStatsService;
use App\Service\ModuleAccessService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/statistiques')]
#[IsGranted('ROLE_USER')]
final class StatisticsController extends AbstractController
{
    #[Route('', name: 'app_statistics_index', methods: ['GET'])]
    public function index(ModuleAccessService $moduleAccessService, DashboardStatsService $dashboardStatsService): Response
    {
        $this->denyAccessUnlessGranted(ModuleAccessVoter::ACCESS, 'statistics');

        $user = $this->getUser();
        \assert($user instanceof User);

        $modules = $moduleAccessService->getAccessibleModules($user);

        return $this->render('statistics/index.html.twig', [
            'dashboard' => $dashboardStatsService->buildDashboard($user, $modules),
        ]);
    }
}
