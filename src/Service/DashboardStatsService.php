<?php

namespace App\Service;

use App\Entity\AppModule;
use App\Entity\Intervention;
use App\Entity\User;
use App\Repository\AppModuleRepository;
use App\Repository\IntervenantRepository;
use App\Repository\InterventionRepository;
use App\Repository\MaintenanceContractRepository;
use App\Repository\UserRepository;
use App\Service\Expense\ExpenseService;

final readonly class DashboardStatsService
{
    public function __construct(
        private PasswordEntryService $passwordEntryService,
        private DocumentService $documentService,
        private ContactService $contactService,
        private ExpenseService $expenseService,
        private MaintenanceContractRepository $contractRepository,
        private InterventionRepository $interventionRepository,
        private IntervenantRepository $intervenantRepository,
        private UserRepository $userRepository,
        private AppModuleRepository $moduleRepository,
    ) {
    }

    /**
     * @param list<AppModule> $modules
     *
     * @return list<array<string, mixed>>
     */
    public function build(User $user, array $modules): array
    {
        $sections = [];
        foreach ($modules as $module) {
            $section = match ($module->getSlug()) {
                'maintenance' => $this->maintenanceSection($module),
                'passwords' => $this->passwordSection($module, $user),
                'documents' => $this->documentSection($module, $user),
                'contacts' => $this->contactSection($module, $user),
                'expenses' => $this->expenseSection($module, $user),
                'users' => $this->usersSection($module),
                'modules' => $this->modulesSection($module),
                default => null,
            };

            if ($section !== null) {
                $section['chart'] ??= $this->sectionChart($section);
                $section['chart_type'] ??= $this->sectionChartType((string) $section['slug']);
                $sections[] = $section;
            }
        }

        return $sections;
    }

    /**
     * @param list<AppModule> $modules
     *
     * @return array<string, mixed>
     */
    public function buildDashboard(User $user, array $modules): array
    {
        $sections = $this->build($user, $modules);
        $slugs = array_map(static fn (AppModule $module): ?string => $module->getSlug(), $modules);
        $hasModule = static fn (string $slug): bool => in_array($slug, $slugs, true);

        $maintenance = $hasModule('maintenance') ? $this->maintenanceOverview() : [
            'active_contracts' => 0,
            'total_contracts' => 0,
            'intervenants' => 0,
            'planned_interventions' => 0,
            'running_interventions' => 0,
            'completed_interventions' => 0,
            'expiring_contracts' => [],
            'open_interventions' => [],
            'status_chart' => [],
            'health_chart' => [],
        ];
        $passwords = $hasModule('passwords') ? $this->passwordOverview($user) : ['total' => 0, 'active' => 0, 'pending' => 0];
        $documents = $hasModule('documents') ? $this->documentOverview($user) : ['total' => 0, 'active' => 0, 'archived' => 0, 'shared' => 0];
        $contacts = $hasModule('contacts') ? $this->contactOverview($user) : ['total' => 0, 'active' => 0, 'types' => 0];
        $expenses = $hasModule('expenses') ? $this->expenseOverview($user) : ['month_total' => 0.0, 'pending_count' => 0, 'paid_total' => 0.0, 'refused_count' => 0, 'validated_count' => 0, 'categories' => [], 'category_chart' => []];
        $users = $hasModule('users') ? $this->usersOverview() : ['total' => 0, 'active' => 0, 'inactive' => 0];
        $modulesOverview = $hasModule('modules') ? $this->modulesOverview() : ['total' => count($modules), 'active' => count($modules)];

        $alertCount = count($maintenance['expiring_contracts']) + $passwords['pending'] + $users['inactive'] + $expenses['pending_count'];
        $kpis = [];

        if ($hasModule('maintenance')) {
            $kpis[] = [
                'label' => 'Contrats',
                'value' => $maintenance['active_contracts'],
                'icon' => 'bi-file-earmark-check',
                'tone' => 'warning',
                'route' => 'app_maintenance_contract_index',
                'hint' => sprintf('%d alerte%s échéance', count($maintenance['expiring_contracts']), count($maintenance['expiring_contracts']) > 1 ? 's' : ''),
            ];
            $kpis[] = [
                'label' => 'Interventions',
                'value' => $maintenance['planned_interventions'] + $maintenance['running_interventions'],
                'icon' => 'bi-activity',
                'tone' => 'primary',
                'route' => 'app_maintenance_intervention_index',
                'hint' => sprintf('%d en cours', $maintenance['running_interventions']),
            ];
        }

        if ($hasModule('documents')) {
            $kpis[] = [
                'label' => 'Documents',
                'value' => $documents['total'],
                'icon' => 'bi-folder2-open',
                'tone' => 'info',
                'route' => 'app_document_index',
                'hint' => sprintf('%d actif%s, %d partagé%s', $documents['active'], $documents['active'] > 1 ? 's' : '', $documents['shared'], $documents['shared'] > 1 ? 's' : ''),
            ];
        }

        if ($hasModule('contacts')) {
            $kpis[] = [
                'label' => 'Contacts',
                'value' => $contacts['total'],
                'icon' => 'bi-person-lines-fill',
                'tone' => 'success',
                'route' => 'app_contact_index',
                'hint' => sprintf('%d type%s renseigné%s', $contacts['types'], $contacts['types'] > 1 ? 's' : '', $contacts['types'] > 1 ? 's' : ''),
            ];
        }

        if ($hasModule('expenses')) {
            $kpis[] = [
                'label' => 'Dépenses',
                'value' => (int) round($expenses['month_total']),
                'value_display' => number_format($expenses['month_total'], 0, ',', ' ').' €',
                'icon' => 'bi-cash-coin',
                'tone' => 'warning',
                'route' => 'app_expense_index',
                'hint' => sprintf('%d en attente', $expenses['pending_count']),
            ];
        }

        if ($hasModule('passwords')) {
            $kpis[] = [
                'label' => 'Accès',
                'value' => $passwords['total'],
                'icon' => 'bi-key',
                'tone' => 'danger',
                'route' => 'app_password_index',
                'hint' => sprintf('%d à valider', $passwords['pending']),
            ];
        }

        if ($hasModule('users')) {
            $kpis[] = [
                'label' => 'Utilisateurs',
                'value' => $users['total'],
                'icon' => 'bi-people',
                'tone' => 'secondary',
                'route' => 'app_user_index',
                'hint' => sprintf('%d actif%s', $users['active'], $users['active'] > 1 ? 's' : ''),
            ];
        }

        if ($hasModule('maintenance')) {
            $kpis[] = [
                'label' => 'Maintenance',
                'value' => $maintenance['intervenants'],
                'icon' => 'bi-tools',
                'tone' => 'purple',
                'route' => 'app_maintenance_intervenant_index',
                'hint' => sprintf('%d prévue%s', $maintenance['planned_interventions'], $maintenance['planned_interventions'] > 1 ? 's' : ''),
            ];
        }

        return [
            'kpis' => $kpis,
            'charts' => [
                'interventions' => $maintenance['status_chart'],
                'contracts_health' => $maintenance['health_chart'],
                'expense_types' => $expenses['category_chart'],
            ],
            'expense_type_chart' => $expenses['category_chart'],
            'alert_count' => $alertCount,
            'alerts' => $this->dashboardAlerts($maintenance['expiring_contracts'], $passwords['pending'], $users['inactive'], $expenses['pending_count']),
            'agenda' => $maintenance['open_interventions'],
            'sections' => $sections,
            'module_tiles' => [
                ['label' => 'Intervenants', 'value' => $maintenance['intervenants'], 'icon' => 'bi-person-gear', 'tone' => 'warning'],
                ['label' => 'Contacts actifs', 'value' => $contacts['active'], 'icon' => 'bi-person-lines-fill', 'tone' => 'success'],
                ['label' => 'Dépenses à valider', 'value' => $expenses['pending_count'], 'icon' => 'bi-cash-coin', 'tone' => 'warning'],
                ['label' => 'Accès à valider', 'value' => $passwords['pending'], 'icon' => 'bi-hourglass-split', 'tone' => 'danger'],
                ['label' => 'Modules actifs', 'value' => $modulesOverview['active'], 'icon' => 'bi-grid', 'tone' => 'secondary'],
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function maintenanceOverview(): array
    {
        $expiringContracts = $this->contractRepository->findExpiringSoon(10, 6);
        $openInterventions = $this->interventionRepository->findOpen();
        $activeContracts = $this->contractRepository->count(['isActive' => true, 'isDeleted' => false]);
        $totalContracts = $this->contractRepository->count(['isDeleted' => false]);
        $intervenants = $this->intervenantRepository->count(['isActive' => true, 'isDeleted' => false]);
        $planned = $this->interventionRepository->count(['status' => 'planifiee', 'isActive' => true, 'isDeleted' => false]);
        $toPlan = $this->interventionRepository->count(['status' => 'a_planifier', 'isActive' => true, 'isDeleted' => false]);
        $running = $this->interventionRepository->count(['status' => 'en_cours', 'isActive' => true, 'isDeleted' => false]);
        $completed = $this->interventionRepository->count(['status' => 'terminee', 'isActive' => true, 'isDeleted' => false]);
        $cancelled = $this->interventionRepository->count(['status' => 'annulee', 'isActive' => true, 'isDeleted' => false]);

        return [
            'active_contracts' => $activeContracts,
            'total_contracts' => $totalContracts,
            'intervenants' => $intervenants,
            'planned_interventions' => $planned + $toPlan,
            'running_interventions' => $running,
            'completed_interventions' => $completed,
            'expiring_contracts' => $expiringContracts,
            'open_interventions' => $this->interventionAgendaItems($openInterventions),
            'status_chart' => $this->withPercentages([
                ['label' => 'À planifier', 'value' => $toPlan, 'tone' => 'secondary'],
                ['label' => 'Planifiées', 'value' => $planned, 'tone' => 'primary'],
                ['label' => 'En cours', 'value' => $running, 'tone' => 'warning'],
                ['label' => 'Terminées', 'value' => $completed, 'tone' => 'success'],
                ['label' => 'Annulées', 'value' => $cancelled, 'tone' => 'danger'],
            ]),
            'health_chart' => $this->withPercentages([
                ['label' => 'Actifs', 'value' => $activeContracts, 'tone' => 'success'],
                ['label' => 'Échéance 10j', 'value' => count($expiringContracts), 'tone' => 'warning'],
                ['label' => 'Inactifs', 'value' => max(0, $totalContracts - $activeContracts), 'tone' => 'secondary'],
            ]),
        ];
    }

    /**
     * @param list<Intervention> $interventions
     *
     * @return list<array{title: string, meta: string, status: string, status_label: string}>
     */
    private function interventionAgendaItems(array $interventions): array
    {
        $statusLabels = array_flip(Intervention::STATUSES);

        return array_map(function (Intervention $intervention) use ($statusLabels): array {
            $planned = $intervention->getPlannedAt()?->format('d/m/Y H:i');
            $started = $intervention->getStartedAt()?->format('d/m/Y H:i');

            $dateLabel = match (true) {
                $intervention->getStatus() === 'en_cours' && $started !== null => 'Démarrée le '.$started,
                $planned !== null => 'Prévue le '.$planned,
                default => 'À planifier',
            };

            return [
                'title' => (string) $intervention->getTitle(),
                'meta' => sprintf(
                    '%s · %s',
                    $intervention->getIntervenant()?->getDisplayName() ?? $intervention->getCustomerName(),
                    $dateLabel,
                ),
                'status' => $intervention->getStatus(),
                'status_label' => $statusLabels[$intervention->getStatus()] ?? str_replace('_', ' ', $intervention->getStatus()),
            ];
        }, $interventions);
    }

    /** @return array{total: int, active: int, pending: int} */
    private function passwordOverview(User $user): array
    {
        $entries = $this->passwordEntryService->getVisibleEntries($user);

        return [
            'total' => count($entries),
            'active' => count(array_filter($entries, static fn ($entry): bool => $entry->isActive())),
            'pending' => $this->passwordEntryService->countPendingValidation($user),
        ];
    }

    /** @return array{total: int, active: int, archived: int, shared: int} */
    private function documentOverview(User $user): array
    {
        return $this->documentService->dashboardStats($user);
    }

    /** @return array{total: int, active: int, types: int} */
    private function contactOverview(User $user): array
    {
        $contacts = $this->contactService->getVisibleContacts($user);
        $types = array_unique(array_map(static fn ($contact): string => (string) $contact->getType(), $contacts));

        return [
            'total' => count($contacts),
            'active' => count(array_filter($contacts, static fn ($contact): bool => $contact->isActive())),
            'types' => count($types),
        ];
    }

    /** @return array{month_total: float, pending_count: int, paid_total: float, refused_count: int, validated_count: int, categories: list<array{label: string, total: string}>, category_chart: list<array<string, mixed>>} */
    private function expenseOverview(User $user): array
    {
        /** @var array{month_total: float, pending_count: int, paid_total: float, refused_count: int, validated_count: int, categories: list<array{label: string, total: string}>} $stats */
        $stats = $this->expenseService->stats($user);
        $stats['category_chart'] = $this->expenseCategoryChart($stats['categories']);

        return $stats;
    }

    /** @return array{total: int, active: int, inactive: int} */
    private function usersOverview(): array
    {
        $total = $this->userRepository->count([]);
        $active = $this->userRepository->count(['isActive' => true]);

        return ['total' => $total, 'active' => $active, 'inactive' => max(0, $total - $active)];
    }

    /** @return array{total: int, active: int} */
    private function modulesOverview(): array
    {
        return [
            'total' => $this->moduleRepository->count([]),
            'active' => $this->moduleRepository->count(['isActive' => true]),
        ];
    }

    /**
     * @param list<array{label: string, value: int, tone: string}> $items
     *
     * @return list<array{label: string, value: int, tone: string, percent: int}>
     */
    private function withPercentages(array $items): array
    {
        $max = max(1, ...array_map(static fn (array $item): int => $item['value'], $items));

        return array_map(static fn (array $item): array => $item + [
            'percent' => $item['value'] > 0 ? max(8, (int) round(($item['value'] / $max) * 100)) : 0,
        ], $items);
    }

    /**
     * @param array<string, mixed> $section
     *
     * @return list<array{label: string, short_label: string, value: int, percent: int, share: int, offset: int}>
     */
    private function sectionChart(array $section): array
    {
        $metrics = array_map(
            static fn (array $metric): array => [
                'label' => (string) $metric['label'],
                'short_label' => mb_substr((string) $metric['label'], 0, 10),
                'value' => (int) $metric['value'],
            ],
            array_slice($section['metrics'] ?? [], 0, 4),
        );
        $max = max(1, ...array_map(static fn (array $metric): int => $metric['value'], $metrics ?: [['value' => 0]]));
        $total = max(1, array_sum(array_map(static fn (array $metric): int => $metric['value'], $metrics)));
        $offset = 0;
        $chart = [];

        foreach ($metrics as $metric) {
            $share = $metric['value'] > 0 ? (int) round(($metric['value'] / $total) * 100) : 0;
            $chart[] = $metric + [
                'percent' => $metric['value'] > 0 ? max(12, (int) round(($metric['value'] / $max) * 100)) : 0,
                'share' => $share,
                'offset' => $offset,
            ];
            $offset += $share;
        }

        return $chart;
    }

    /**
     * @param list<array{label: string, total: string}> $categories
     *
     * @return list<array{label: string, short_label: string, value: int, display: string, percent: int, share: int, offset: int, tone: string}>
     */
    private function expenseCategoryChart(array $categories): array
    {
        $items = array_slice($categories, 0, 6);
        if ($items === []) {
            return [];
        }

        $amounts = array_map(static fn (array $item): float => (float) $item['total'], $items);
        $max = max(1.0, ...$amounts);
        $total = max(1.0, array_sum($amounts));
        $offset = 0;
        $chart = [];
        $tones = ['primary', 'success', 'warning', 'danger', 'info', 'secondary'];

        foreach ($items as $index => $item) {
            $label = (string) $item['label'];
            $amount = (float) $item['total'];
            $share = $amount > 0 ? (int) round(($amount / $total) * 100) : 0;
            $chart[] = [
                'label' => $label,
                'short_label' => mb_substr($label, 0, 12),
                'value' => (int) round($amount),
                'display' => number_format($amount, 0, ',', ' ').' EUR',
                'percent' => $amount > 0 ? max(8, (int) round(($amount / $max) * 100)) : 0,
                'share' => $share,
                'offset' => $offset,
                'tone' => $tones[$index % count($tones)],
            ];
            $offset += $share;
        }

        return $chart;
    }

    private function sectionChartType(string $slug): string
    {
        return match ($slug) {
            'passwords', 'contacts', 'users', 'expenses' => 'pie',
            default => 'bars',
        };
    }

    /**
     * @param list<object> $expiringContracts
     *
     * @return list<array{title: string, text: string, icon: string, tone: string}>
     */
    private function dashboardAlerts(array $expiringContracts, int $pendingPasswords, int $inactiveUsers, int $pendingExpenses): array
    {
        $alerts = array_map(static fn ($contract): array => [
            'title' => (string) $contract->getReference(),
            'text' => sprintf('%s arrive à échéance le %s', $contract->getCustomerName(), $contract->getEndDate()?->format('d/m/Y') ?? '-'),
            'icon' => 'bi-calendar-event',
            'tone' => 'warning',
        ], $expiringContracts);

        if ($pendingPasswords > 0) {
            $alerts[] = [
                'title' => 'Validation des accès',
                'text' => sprintf('%d mot%s de passe en attente de validation.', $pendingPasswords, $pendingPasswords > 1 ? 's' : ''),
                'icon' => 'bi-key',
                'tone' => 'danger',
            ];
        }

        if ($pendingExpenses > 0) {
            $alerts[] = [
                'title' => 'Dépenses à valider',
                'text' => sprintf('%d dépense%s en attente de validation.', $pendingExpenses, $pendingExpenses > 1 ? 's' : ''),
                'icon' => 'bi-cash-coin',
                'tone' => 'warning',
            ];
        }

        if ($inactiveUsers > 0) {
            $alerts[] = [
                'title' => 'Comptes inactifs',
                'text' => sprintf('%d compte%s utilisateur%s à vérifier.', $inactiveUsers, $inactiveUsers > 1 ? 's' : '', $inactiveUsers > 1 ? 's' : ''),
                'icon' => 'bi-person-slash',
                'tone' => 'secondary',
            ];
        }

        return array_slice($alerts, 0, 7);
    }

    /** @return array<string, mixed> */
    private function maintenanceSection(AppModule $module): array
    {
        $expiringContracts = $this->contractRepository->findExpiringSoon(10, 5);
        $upcomingInterventions = $this->interventionRepository->findUpcoming(5);
        $activeContracts = $this->contractRepository->count(['isActive' => true, 'isDeleted' => false]);
        $intervenants = $this->intervenantRepository->count(['isActive' => true, 'isDeleted' => false]);
        $inProgress = $this->interventionRepository->count(['status' => 'en_cours', 'isActive' => true, 'isDeleted' => false]);

        return [
            'slug' => 'maintenance',
            'name' => 'Contrats de maintenance',
            'icon' => 'bi-tools',
            'route' => $module->getRouteName(),
            'tone' => 'warning',
            'headline' => sprintf('%d contrat%s actif%s', $activeContracts, $activeContracts > 1 ? 's' : '', $activeContracts > 1 ? 's' : ''),
            'metrics' => [
                ['label' => 'Contrats actifs', 'value' => $activeContracts, 'icon' => 'bi-file-earmark-check'],
                ['label' => 'Intervenants', 'value' => $intervenants, 'icon' => 'bi-person-gear'],
                ['label' => 'En cours', 'value' => $inProgress, 'icon' => 'bi-activity'],
                ['label' => 'Alertes 10 jours', 'value' => count($expiringContracts), 'icon' => 'bi-bell'],
            ],
            'alerts' => array_map(static fn ($contract): array => [
                'title' => (string) $contract->getReference(),
                'text' => sprintf('%s arrive à échéance le %s', $contract->getCustomerName(), $contract->getEndDate()?->format('d/m/Y') ?? '-'),
                'level' => 'warning',
            ], $expiringContracts),
            'items_title' => 'Prochaines interventions',
            'items' => array_map(static fn ($intervention): array => [
                'title' => (string) $intervention->getTitle(),
                'text' => sprintf('%s · %s', $intervention->getIntervenant()?->getDisplayName() ?? $intervention->getCustomerName(), $intervention->getPlannedAt()?->format('d/m/Y H:i') ?? 'Non planifiée'),
            ], $upcomingInterventions),
            'progress' => $activeContracts > 0 ? min(100, (int) round((count($expiringContracts) / $activeContracts) * 100)) : 0,
            'progress_label' => 'Part des contrats en alerte',
        ];
    }

    /** @return array<string, mixed> */
    private function passwordSection(AppModule $module, User $user): array
    {
        $entries = $this->passwordEntryService->getVisibleEntries($user);
        $pending = $this->passwordEntryService->countPendingValidation($user);
        $active = count(array_filter($entries, static fn ($entry): bool => $entry->isActive()));

        return [
            'slug' => 'passwords',
            'name' => 'Coffre de mots de passe',
            'icon' => 'bi-key',
            'route' => $module->getRouteName(),
            'tone' => 'primary',
            'headline' => sprintf('%d accès visible%s', count($entries), count($entries) > 1 ? 's' : ''),
            'metrics' => [
                ['label' => 'Accès visibles', 'value' => count($entries), 'icon' => 'bi-key'],
                ['label' => 'Actifs', 'value' => $active, 'icon' => 'bi-check-circle'],
                ['label' => 'À valider', 'value' => $pending, 'icon' => 'bi-hourglass-split'],
            ],
            'alerts' => $pending > 0 ? [[
                'title' => 'Validation requise',
                'text' => sprintf('%d mot%s de passe en attente.', $pending, $pending > 1 ? 's' : ''),
                'level' => 'warning',
            ]] : [],
            'items_title' => 'État du coffre',
            'items' => [
                ['title' => 'Partage sécurisé', 'text' => 'Surveillez les accès en attente et les comptes inactifs.'],
            ],
            'progress' => count($entries) > 0 ? (int) round(($active / count($entries)) * 100) : 0,
            'progress_label' => 'Accès actifs',
        ];
    }

    /** @return array<string, mixed> */
    private function documentSection(AppModule $module, User $user): array
    {
        $result = $this->documentService->dashboardStats($user);

        return [
            'slug' => 'documents',
            'name' => 'Documents',
            'icon' => 'bi-folder2-open',
            'route' => $module->getRouteName(),
            'tone' => 'info',
            'headline' => sprintf('%d document%s accessible%s', $result['total'], $result['total'] > 1 ? 's' : '', $result['total'] > 1 ? 's' : ''),
            'metrics' => [
                ['label' => 'Documents', 'value' => $result['total'], 'icon' => 'bi-folder2-open'],
                ['label' => 'Actifs', 'value' => $result['active'], 'icon' => 'bi-check-circle'],
                ['label' => 'Archivés', 'value' => $result['archived'], 'icon' => 'bi-archive'],
                ['label' => 'Partagés', 'value' => $result['shared'], 'icon' => 'bi-share'],
            ],
            'alerts' => [],
            'items_title' => 'Suivi documents',
            'items' => [
                ['title' => 'Partages', 'text' => 'Gardez les documents utiles à portée et retirez les accès obsolètes.'],
            ],
            'progress' => min(100, $result['total'] * 10),
            'progress_label' => 'Volume documentaire',
        ];
    }

    /** @return array<string, mixed> */
    private function contactSection(AppModule $module, User $user): array
    {
        $contacts = $this->contactService->getVisibleContacts($user);
        $active = count(array_filter($contacts, static fn ($contact): bool => $contact->isActive()));
        $types = array_unique(array_map(static fn ($contact): string => (string) $contact->getType(), $contacts));

        return [
            'slug' => 'contacts',
            'name' => 'Carnet de contacts',
            'icon' => 'bi-person-lines-fill',
            'route' => $module->getRouteName(),
            'tone' => 'success',
            'headline' => sprintf('%d contact%s visible%s', count($contacts), count($contacts) > 1 ? 's' : '', count($contacts) > 1 ? 's' : ''),
            'metrics' => [
                ['label' => 'Contacts', 'value' => count($contacts), 'icon' => 'bi-person-lines-fill'],
                ['label' => 'Actifs', 'value' => $active, 'icon' => 'bi-check-circle'],
                ['label' => 'Types', 'value' => count($types), 'icon' => 'bi-tags'],
            ],
            'alerts' => [],
            'items_title' => 'Qualité du carnet',
            'items' => [],
            'progress' => count($contacts) > 0 ? (int) round(($active / count($contacts)) * 100) : 0,
            'progress_label' => 'Contacts actifs',
        ];
    }

    /** @return array<string, mixed> */
    private function expenseSection(AppModule $module, User $user): array
    {
        $stats = $this->expenseOverview($user);
        $categoryItems = array_map(static fn (array $item): array => [
            'title' => $item['label'],
            'text' => number_format((float) $item['total'], 2, ',', ' ').' € TTC',
        ], array_slice($stats['categories'], 0, 3));

        return [
            'slug' => 'expenses',
            'name' => 'Dépenses',
            'icon' => 'bi-cash-coin',
            'route' => $module->getRouteName(),
            'tone' => 'warning',
            'headline' => sprintf('%s € ce mois-ci', number_format($stats['month_total'], 2, ',', ' ')),
            'metrics' => [
                ['label' => 'Ce mois', 'value' => (int) round($stats['month_total']), 'icon' => 'bi-calendar2-month'],
                ['label' => 'En attente', 'value' => $stats['pending_count'], 'icon' => 'bi-hourglass-split'],
                ['label' => 'Payé', 'value' => (int) round($stats['paid_total']), 'icon' => 'bi-check2-circle'],
                ['label' => 'Refusées', 'value' => $stats['refused_count'], 'icon' => 'bi-x-circle'],
            ],
            'alerts' => $stats['pending_count'] > 0 ? [[
                'title' => 'Validation requise',
                'text' => sprintf('%d dépense%s à traiter.', $stats['pending_count'], $stats['pending_count'] > 1 ? 's' : ''),
                'level' => 'warning',
            ]] : [],
            'items_title' => 'Top catégories',
            'items' => $categoryItems,
            'progress' => $stats['pending_count'] > 0 ? min(100, $stats['pending_count'] * 20) : 0,
            'progress_label' => 'Par type',
            'chart' => $stats['category_chart'],
            'chart_type' => 'bars',
        ];
    }

    /** @return array<string, mixed>|null */
    private function usersSection(AppModule $module): ?array
    {
        $total = $this->userRepository->count([]);
        $active = $this->userRepository->count(['isActive' => true]);

        return [
            'slug' => 'users',
            'name' => 'Utilisateurs',
            'icon' => 'bi-people',
            'route' => $module->getRouteName(),
            'tone' => 'secondary',
            'headline' => sprintf('%d compte%s utilisateur%s', $total, $total > 1 ? 's' : '', $total > 1 ? 's' : ''),
            'metrics' => [
                ['label' => 'Comptes', 'value' => $total, 'icon' => 'bi-people'],
                ['label' => 'Actifs', 'value' => $active, 'icon' => 'bi-person-check'],
                ['label' => 'Inactifs', 'value' => max(0, $total - $active), 'icon' => 'bi-person-slash'],
            ],
            'alerts' => $total > $active ? [[
                'title' => 'Comptes inactifs',
                'text' => sprintf('%d compte%s à vérifier.', $total - $active, ($total - $active) > 1 ? 's' : ''),
                'level' => 'secondary',
            ]] : [],
            'items_title' => 'Gestion des accès',
            'items' => [
                ['title' => 'Rôles', 'text' => 'Les rôles sensibles restent réservés aux super administrateurs.'],
            ],
            'progress' => $total > 0 ? (int) round(($active / $total) * 100) : 0,
            'progress_label' => 'Comptes actifs',
        ];
    }

    /** @return array<string, mixed>|null */
    private function modulesSection(AppModule $module): ?array
    {
        $total = $this->moduleRepository->count([]);
        $active = $this->moduleRepository->count(['isActive' => true]);

        return [
            'slug' => 'modules',
            'name' => 'Configuration',
            'icon' => 'bi-sliders',
            'route' => $module->getRouteName(),
            'tone' => 'dark',
            'headline' => sprintf('%d module%s actif%s', $active, $active > 1 ? 's' : '', $active > 1 ? 's' : ''),
            'metrics' => [
                ['label' => 'Modules', 'value' => $total, 'icon' => 'bi-grid'],
                ['label' => 'Actifs', 'value' => $active, 'icon' => 'bi-check-circle'],
            ],
            'alerts' => [],
            'items_title' => 'Paramétrage',
            'items' => [
                ['title' => 'Catalogue', 'text' => 'Activez uniquement les modules utiles à vos équipes.'],
            ],
            'progress' => $total > 0 ? (int) round(($active / $total) * 100) : 0,
            'progress_label' => 'Modules actifs',
        ];
    }
}
