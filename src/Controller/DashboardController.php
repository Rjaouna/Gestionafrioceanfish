<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\DashboardStatsService;
use App\Service\FactoryUnitService;
use App\Service\ModuleAccessService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
final class DashboardController extends AbstractController
{
    #[Route('/', name: 'app_dashboard', methods: ['GET'])]
    public function index(Request $request, ModuleAccessService $moduleAccessService, DashboardStatsService $dashboardStatsService, FactoryUnitService $factoryUnitService): Response
    {
        $user = $this->getUser();
        \assert($user instanceof User);

        $modules = $moduleAccessService->getAccessibleModules($user);
        $filters = [
            'period' => $request->query->get('period', 'month'),
            'from' => $request->query->get('from'),
            'to' => $request->query->get('to'),
        ];
        $canViewFactoryStorage = $moduleAccessService->canAccess($user, 'factory')
            || $moduleAccessService->canAccess($user, 'receptions')
            || $moduleAccessService->canAccess($user, 'cout-revient');

        return $this->render('dashboard/index.html.twig', [
            'modules' => $modules,
            'dashboard' => $dashboardStatsService->buildDashboard($user, $modules, $filters),
            'factory_storage_overview' => $canViewFactoryStorage ? $factoryUnitService->storageOverview($user) : null,
        ]);
    }
}
