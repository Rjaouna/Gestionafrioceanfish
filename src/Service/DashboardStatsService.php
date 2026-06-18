<?php

namespace App\Service;

use App\Entity\AppModule;
use App\Entity\Appointment;
use App\Entity\Expense;
use App\Entity\InventoryItem;
use App\Entity\Intervention;
use App\Entity\User;
use App\Repository\AppModuleRepository;
use App\Repository\AppointmentRepository;
use App\Repository\ExpenseRepository;
use App\Repository\InventoryItemRepository;
use App\Repository\InventoryMovementRepository;
use App\Repository\InventoryRequestRepository;
use App\Repository\IntervenantRepository;
use App\Repository\InterventionRepository;
use App\Repository\MaintenanceContractRepository;
use App\Repository\UserRepository;
use App\Service\Appointment\AppointmentAccessService;
use App\Service\Expense\ExpenseAccessService;
use App\Service\Expense\ExpenseService;
use App\Service\Inventory\InventoryAccessService;
use App\Service\Trash\TrashService;

final readonly class DashboardStatsService
{
    public function __construct(
        private PasswordEntryService $passwordEntryService,
        private DocumentService $documentService,
        private ContactService $contactService,
        private ExpenseService $expenseService,
        private ExpenseRepository $expenseRepository,
        private ExpenseAccessService $expenseAccess,
        private MaintenanceContractRepository $contractRepository,
        private InterventionRepository $interventionRepository,
        private IntervenantRepository $intervenantRepository,
        private UserRepository $userRepository,
        private AppModuleRepository $moduleRepository,
        private AppointmentRepository $appointmentRepository,
        private AppointmentAccessService $appointmentAccess,
        private InventoryAccessService $inventoryAccess,
        private InventoryItemRepository $inventoryItemRepository,
        private InventoryRequestRepository $inventoryRequestRepository,
        private InventoryMovementRepository $inventoryMovementRepository,
        private TrashService $trashService,
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
                'agenda' => $this->agendaSection($module, $user),
                'inventory' => $this->inventorySection($module, $user),
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
    public function buildDashboard(User $user, array $modules, array $filters = []): array
    {
        $sections = $this->build($user, $modules);
        $slugs = array_map(static fn (AppModule $module): ?string => $module->getSlug(), $modules);
        $hasModule = static fn (string $slug): bool => in_array($slug, $slugs, true);
        $period = $this->dashboardPeriod($filters);

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
        $expenses = $hasModule('expenses') ? $this->expenseOverview($user, $period) : [
            'month_total' => 0.0,
            'period_total' => 0.0,
            'period_count' => 0,
            'period_pending_count' => 0,
            'pending_count' => 0,
            'paid_total' => 0.0,
            'refused_count' => 0,
            'validated_count' => 0,
            'categories' => [],
            'category_chart' => [],
            'status_chart' => [],
            'recent' => [],
        ];
        $users = $hasModule('users') ? $this->usersOverview() : ['total' => 0, 'active' => 0, 'inactive' => 0];
        $agenda = $hasModule('agenda') ? $this->appointmentOverview($user, $period) : [
            'total' => 0,
            'period_count' => 0,
            'today' => 0,
            'next_7_days' => 0,
            'pending' => 0,
            'high_priority' => 0,
            'upcoming' => [],
            'status_chart' => [],
            'type_chart' => [],
            'priority_chart' => [],
        ];
        $inventory = $hasModule('inventory') ? $this->inventoryOverview($user) : [
            'total_items' => 0,
            'active_items' => 0,
            'archived_items' => 0,
            'assigned_items' => 0,
            'unavailable_items' => 0,
            'maintenance_items' => 0,
            'lost_items' => 0,
            'pending_requests' => 0,
            'pending_transfers' => 0,
            'pending_inventories' => 0,
            'recent_movements' => [],
            'status_chart' => [],
            'category_chart' => [],
            'site_chart' => [],
            'logistics_chart' => [],
            'request_chart' => [],
        ];
        $trash = $this->trashOverview($user);

        $healthCards = $this->healthCards($agenda, $maintenance, $expenses, $passwords, $users, $inventory, $hasModule);
        $alertCount = count($maintenance['expiring_contracts'])
            + $passwords['pending']
            + $users['inactive']
            + $expenses['pending_count']
            + $agenda['pending']
            + $agenda['high_priority']
            + $inventory['pending_requests']
            + $inventory['unavailable_items'];
        $kpis = [];

        if ($hasModule('agenda')) {
            $kpis[] = [
                'label' => 'Agenda',
                'value' => $agenda['today'],
                'icon' => 'bi-calendar2-week',
                'tone' => 'primary',
                'route' => 'app_appointment_calendar',
                'hint' => sprintf('%d RDV dans les 7 jours', $agenda['next_7_days']),
            ];
        }

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
                'value' => (int) round($expenses['period_total']),
                'value_display' => number_format($expenses['period_total'], 0, ',', ' ').' dh',
                'icon' => 'bi-cash-coin',
                'tone' => 'warning',
                'route' => 'app_expense_index',
                'hint' => sprintf('%d sur la période, %d en attente', $expenses['period_count'], $expenses['pending_count']),
            ];
        }

        if ($hasModule('inventory')) {
            $kpis[] = [
                'label' => 'Inventaire',
                'value' => $inventory['active_items'],
                'icon' => 'bi-box-seam',
                'tone' => $inventory['pending_requests'] > 0 ? 'warning' : 'primary',
                'route' => 'app_inventory_dashboard',
                'hint' => sprintf('%d demande%s à valider, %d à surveiller', $inventory['pending_requests'], $inventory['pending_requests'] > 1 ? 's' : '', $inventory['unavailable_items']),
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
            'period' => $period,
            'kpis' => $kpis,
            'executive_kpis' => $this->executiveKpis($agenda, $maintenance, $expenses, $passwords, $documents, $contacts, $users, $inventory, $hasModule),
            'hero_metrics' => $this->heroMetrics($agenda, $maintenance, $expenses, $inventory, $alertCount, count($modules)),
            'health_cards' => $healthCards,
            'health_chart' => $this->healthChart($healthCards),
            'operational_chart' => $this->operationalChart($agenda, $maintenance, $expenses, $passwords, $inventory),
            'schedule' => $this->scheduleItems($agenda['upcoming'], $maintenance['open_interventions']),
            'recent_activity' => $this->recentActivity($user, $hasModule),
            'quick_actions' => $this->quickActions($hasModule, $trash),
            'agenda_overview' => $agenda,
            'finance_overview' => $expenses,
            'inventory_overview' => $inventory,
            'trash_overview' => $trash,
            'available' => [
                'agenda' => $hasModule('agenda'),
                'expenses' => $hasModule('expenses'),
                'inventory' => $hasModule('inventory'),
                'maintenance' => $hasModule('maintenance'),
                'documents' => $hasModule('documents'),
                'contacts' => $hasModule('contacts'),
                'passwords' => $hasModule('passwords'),
                'users' => $hasModule('users'),
                'modules' => $hasModule('modules'),
                'trash' => $trash['available'],
            ],
            'charts' => [
                'agenda' => $agenda['status_chart'],
                'agenda_types' => $agenda['type_chart'],
                'agenda_priorities' => $agenda['priority_chart'],
                'interventions' => $maintenance['status_chart'],
                'contracts_health' => $maintenance['health_chart'],
                'expense_types' => $expenses['category_chart'],
                'expense_status' => $expenses['status_chart'],
                'inventory_status' => $inventory['status_chart'],
                'inventory_categories' => $inventory['category_chart'],
                'inventory_sites' => $inventory['site_chart'],
                'inventory_logistics' => $inventory['logistics_chart'],
                'inventory_requests' => $inventory['request_chart'],
            ],
            'expense_type_chart' => $expenses['category_chart'],
            'alert_count' => $alertCount,
            'alerts' => $this->dashboardAlerts($maintenance['expiring_contracts'], $passwords['pending'], $users['inactive'], $expenses['pending_count'], $agenda['pending'], $agenda['high_priority'], $inventory),
            'agenda' => $maintenance['open_interventions'],
            'sections' => $sections,
            'module_tiles' => [
                ['label' => 'Demandes d’inventaire', 'value' => $inventory['pending_requests'], 'icon' => 'bi-clipboard-check', 'tone' => 'info'],
                ['label' => 'RDV aujourd’hui', 'value' => $agenda['today'], 'icon' => 'bi-calendar2-check', 'tone' => 'primary'],
                ['label' => 'Intervenants', 'value' => $maintenance['intervenants'], 'icon' => 'bi-person-gear', 'tone' => 'warning'],
                ['label' => 'Contacts actifs', 'value' => $contacts['active'], 'icon' => 'bi-person-lines-fill', 'tone' => 'success'],
                ['label' => 'Dépenses à valider', 'value' => $expenses['pending_count'], 'icon' => 'bi-cash-coin', 'tone' => 'warning'],
                ['label' => 'Accès à valider', 'value' => $passwords['pending'], 'icon' => 'bi-hourglass-split', 'tone' => 'danger'],
            ],
        ];
    }

    /**
     * @param callable(string): bool $hasModule
     *
     * @return list<array<string, mixed>>
     */
    private function executiveKpis(array $agenda, array $maintenance, array $expenses, array $passwords, array $documents, array $contacts, array $users, array $inventory, callable $hasModule): array
    {
        $items = [];

        if ($hasModule('agenda')) {
            $items[] = [
                'label' => 'RDV aujourd’hui',
                'value' => $agenda['today'],
                'hint' => sprintf('%d dans les 7 prochains jours', $agenda['next_7_days']),
                'icon' => 'bi-calendar2-check',
                'tone' => 'primary',
                'route' => 'app_appointment_calendar',
            ];
        }

        if ($hasModule('expenses')) {
            $items[] = [
                'label' => 'Dépenses période',
                'value' => (int) round($expenses['period_total']),
                'value_display' => number_format($expenses['period_total'], 0, ',', ' ').' dh',
                'hint' => sprintf('%d dépense%s, %d à valider', $expenses['period_count'], $expenses['period_count'] > 1 ? 's' : '', $expenses['period_pending_count']),
                'icon' => 'bi-cash-coin',
                'tone' => 'warning',
                'route' => 'app_expense_index',
            ];
        }

        if ($hasModule('maintenance')) {
            $items[] = [
                'label' => 'Maintenance ouverte',
                'value' => $maintenance['planned_interventions'] + $maintenance['running_interventions'],
                'hint' => sprintf('%d en cours, %d contrats à échéance', $maintenance['running_interventions'], count($maintenance['expiring_contracts'])),
                'icon' => 'bi-tools',
                'tone' => 'success',
                'route' => 'app_maintenance_intervention_index',
            ];
        }

        if ($hasModule('inventory')) {
            $items[] = [
                'label' => 'Inventaire à valider',
                'value' => $inventory['pending_requests'],
                'hint' => sprintf('%d transport%s, %d inventaire%s, %d matériel%s actif%s', $inventory['pending_transfers'], $inventory['pending_transfers'] > 1 ? 's' : '', $inventory['pending_inventories'], $inventory['pending_inventories'] > 1 ? 's' : '', $inventory['active_items'], $inventory['active_items'] > 1 ? 's' : '', $inventory['active_items'] > 1 ? 's' : ''),
                'icon' => 'bi-box-seam',
                'tone' => $inventory['pending_requests'] > 0 ? 'warning' : 'primary',
                'route' => 'app_inventory_request_index',
            ];
        }

        if ($hasModule('documents')) {
            $items[] = [
                'label' => 'Documents utiles',
                'value' => $documents['total'],
                'hint' => sprintf('%d actifs, %d partagés', $documents['active'], $documents['shared']),
                'icon' => 'bi-folder2-open',
                'tone' => 'info',
                'route' => 'app_document_index',
            ];
        }



        if ($hasModule('users')) {
            $items[] = [
                'label' => 'Utilisateurs',
                'value' => $users['active'],
                'hint' => sprintf('%d actifs sur %d comptes', $users['active'], $users['total']),
                'icon' => 'bi-people',
                'tone' => 'secondary',
                'route' => 'app_user_index',
            ];
        }

        return array_slice($items, 0, 8);
    }

    /** @return list<array<string, mixed>> */
    private function heroMetrics(array $agenda, array $maintenance, array $expenses, array $inventory, int $alertCount, int $moduleCount): array
    {
        return [
            ['label' => 'Alertes', 'value' => $alertCount, 'icon' => 'bi-bell', 'tone' => $alertCount > 0 ? 'warning' : 'success'],
            ['label' => 'RDV 7 jours', 'value' => $agenda['next_7_days'], 'icon' => 'bi-calendar-week', 'tone' => 'primary'],
            ['label' => 'Interventions ouvertes', 'value' => $maintenance['planned_interventions'] + $maintenance['running_interventions'], 'icon' => 'bi-activity', 'tone' => 'success'],
            ['label' => 'Dépenses période', 'value' => number_format($expenses['period_total'], 0, ',', ' ').' dh', 'icon' => 'bi-receipt', 'tone' => 'warning'],
            ['label' => 'Demandes d’inventaire', 'value' => $inventory['pending_requests'], 'icon' => 'bi-box-seam', 'tone' => $inventory['pending_requests'] > 0 ? 'warning' : 'primary'],
        ];
    }

    /** @param callable(string): bool $hasModule */
    private function healthCards(array $agenda, array $maintenance, array $expenses, array $passwords, array $users, array $inventory, callable $hasModule): array
    {
        $cards = [];

        if ($hasModule('agenda')) {
            $cards[] = [
                'label' => 'Agenda',
                'value' => $agenda['pending'] + $agenda['high_priority'],
                'caption' => sprintf('%d en attente, %d priorité haute', $agenda['pending'], $agenda['high_priority']),
                'tone' => ($agenda['pending'] + $agenda['high_priority']) > 0 ? 'warning' : 'success',
                'icon' => 'bi-calendar2-week',
            ];
        }

        if ($hasModule('maintenance')) {
            $cards[] = [
                'label' => 'Maintenance',
                'value' => count($maintenance['expiring_contracts']) + $maintenance['running_interventions'],
                'caption' => sprintf('%d contrats à échéance, %d en cours', count($maintenance['expiring_contracts']), $maintenance['running_interventions']),
                'tone' => count($maintenance['expiring_contracts']) > 0 ? 'warning' : 'success',
                'icon' => 'bi-tools',
            ];
        }

        if ($hasModule('expenses')) {
            $cards[] = [
                'label' => 'Finances',
                'value' => $expenses['pending_count'],
                'caption' => sprintf('%d dépenses à valider, %d refusées', $expenses['pending_count'], $expenses['refused_count']),
                'tone' => $expenses['pending_count'] > 0 ? 'warning' : 'success',
                'icon' => 'bi-cash-stack',
            ];
        }

        if ($hasModule('inventory')) {
            $cards[] = [
                'label' => 'Inventaire',
                'value' => $inventory['pending_requests'] + $inventory['unavailable_items'],
                'caption' => sprintf('%d demandes à valider, %d matériels à surveiller', $inventory['pending_requests'], $inventory['unavailable_items']),
                'tone' => ($inventory['pending_requests'] + $inventory['unavailable_items']) > 0 ? 'warning' : 'success',
                'icon' => 'bi-box-seam',
            ];
        }

        if ($hasModule('passwords')) {
            $cards[] = [
                'label' => 'Accès',
                'value' => $passwords['pending'],
                'caption' => sprintf('%d accès en attente de validation', $passwords['pending']),
                'tone' => $passwords['pending'] > 0 ? 'danger' : 'success',
                'icon' => 'bi-key',
            ];
        }

        if ($hasModule('users')) {
            $cards[] = [
                'label' => 'Comptes',
                'value' => $users['inactive'],
                'caption' => sprintf('%d comptes inactifs à vérifier', $users['inactive']),
                'tone' => $users['inactive'] > 0 ? 'secondary' : 'success',
                'icon' => 'bi-person-check',
            ];
        }

        return $cards;
    }

    /** @param list<array<string, mixed>> $cards */
    private function healthChart(array $cards): array
    {
        return $this->withPercentages(array_map(static fn (array $card): array => [
            'label' => (string) $card['label'],
            'short_label' => mb_substr((string) $card['label'], 0, 10),
            'value' => (int) $card['value'],
            'tone' => (string) $card['tone'],
        ], $cards));
    }

    /** @return list<array<string, mixed>> */
    private function operationalChart(array $agenda, array $maintenance, array $expenses, array $passwords, array $inventory): array
    {
        return $this->withPercentages([
            ['label' => 'RDV 7 jours', 'short_label' => 'RDV', 'value' => $agenda['next_7_days'], 'tone' => 'primary'],
            ['label' => 'Interventions ouvertes', 'short_label' => 'Maint.', 'value' => $maintenance['planned_interventions'] + $maintenance['running_interventions'], 'tone' => 'success'],
            ['label' => 'Contrats échéance', 'short_label' => 'Contrats', 'value' => count($maintenance['expiring_contracts']), 'tone' => 'warning'],
            ['label' => 'Dépenses à valider', 'short_label' => 'Dép.', 'value' => $expenses['pending_count'], 'tone' => 'warning'],
            ['label' => 'Accès à valider', 'short_label' => 'Accès', 'value' => $passwords['pending'], 'tone' => 'danger'],
        ]);
    }

    /**
     * @param list<array<string, mixed>> $appointments
     * @param list<array<string, mixed>> $interventions
     *
     * @return list<array<string, mixed>>
     */
    private function scheduleItems(array $appointments, array $interventions): array
    {
        $items = [];

        foreach (array_slice($appointments, 0, 5) as $appointment) {
            $items[] = $appointment + [
                'kind' => 'Rendez-vous',
                'icon' => 'bi-calendar2-check',
                'tone' => 'primary',
                'route' => 'app_appointment_calendar',
            ];
        }

        foreach (array_slice($interventions, 0, 4) as $intervention) {
            $items[] = $intervention + [
                'kind' => 'Intervention',
                'icon' => 'bi-tools',
                'tone' => 'success',
                'route' => 'app_maintenance_intervention_index',
            ];
        }

        return array_slice($items, 0, 7);
    }

    /** @param callable(string): bool $hasModule */
    private function quickActions(callable $hasModule, array $trash): array
    {
        $actions = [];

        if ($hasModule('agenda')) {
            $actions[] = ['label' => 'Ouvrir l’agenda', 'text' => 'Créer ou déplacer un rendez-vous', 'route' => 'app_appointment_calendar', 'icon' => 'bi-calendar-plus', 'tone' => 'primary'];
        }

        if ($hasModule('expenses')) {
            $actions[] = ['label' => 'Valider les dépenses', 'text' => 'Contrôler les demandes en attente', 'route' => 'app_expense_index', 'icon' => 'bi-receipt-cutoff', 'tone' => 'warning'];
        }

        if ($hasModule('maintenance')) {
            $actions[] = ['label' => 'Suivre la maintenance', 'text' => 'Voir interventions et contrats', 'route' => 'app_maintenance_intervention_index', 'icon' => 'bi-tools', 'tone' => 'success'];
        }

        if ($hasModule('inventory')) {
            $actions[] = ['label' => 'Valider inventaire', 'text' => 'Transports et inventaires en attente', 'route' => 'app_inventory_request_index', 'icon' => 'bi-clipboard-check', 'tone' => 'info'];
        }

        if ($hasModule('documents')) {
            $actions[] = ['label' => 'Consulter les documents', 'text' => 'Accéder aux fichiers partagés', 'route' => 'app_document_index', 'icon' => 'bi-folder2-open', 'tone' => 'info'];
        }

        if ($hasModule('contacts')) {
            $actions[] = ['label' => 'Carnet de contacts', 'text' => 'Retrouver clients et partenaires', 'route' => 'app_contact_index', 'icon' => 'bi-person-lines-fill', 'tone' => 'success'];
        }

        if ($trash['available']) {
            $actions[] = ['label' => 'Corbeille', 'text' => sprintf('%d élément%s supprimé%s', $trash['deleted'], $trash['deleted'] > 1 ? 's' : '', $trash['deleted'] > 1 ? 's' : ''), 'route' => 'app_trash_index', 'icon' => 'bi-trash3', 'tone' => 'danger'];
        }

        return $actions;
    }

    /**
     * @param array<string, mixed> $filters
     *
     * @return array{key: string, label: string, start: \DateTimeImmutable, end: \DateTimeImmutable, from_value: string, to_value: string}
     */
    private function dashboardPeriod(array $filters): array
    {
        $key = (string) ($filters['period'] ?? 'month');
        if (!in_array($key, ['today', 'week', 'month', 'year', 'custom'], true)) {
            $key = 'month';
        }

        $today = new \DateTimeImmutable('today');

        if ($key === 'custom') {
            $start = $this->parseDashboardDate($filters['from'] ?? null) ?? $today;
            $endDate = $this->parseDashboardDate($filters['to'] ?? null) ?? $start;

            if ($endDate < $start) {
                [$start, $endDate] = [$endDate, $start];
            }

            $start = $start->setTime(0, 0, 0);
            $end = $endDate->setTime(23, 59, 59);

            return [
                'key' => 'custom',
                'label' => $start->format('d/m/Y').' - '.$end->format('d/m/Y'),
                'start' => $start,
                'end' => $end,
                'from_value' => $start->format('Y-m-d'),
                'to_value' => $end->format('Y-m-d'),
            ];
        }

        [$start, $end, $label] = match ($key) {
            'today' => [$today, $today->setTime(23, 59, 59), 'Aujourd’hui'],
            'week' => [$today->modify('monday this week'), $today->modify('sunday this week')->setTime(23, 59, 59), 'Cette semaine'],
            'year' => [$today->setDate((int) $today->format('Y'), 1, 1), $today->setDate((int) $today->format('Y'), 12, 31)->setTime(23, 59, 59), 'Cette année'],
            default => [$today->modify('first day of this month'), $today->modify('last day of this month')->setTime(23, 59, 59), 'Ce mois-ci'],
        };

        $start = $start->setTime(0, 0, 0);

        return [
            'key' => $key,
            'label' => $label,
            'start' => $start,
            'end' => $end,
            'from_value' => $start->format('Y-m-d'),
            'to_value' => $end->format('Y-m-d'),
        ];
    }

    private function parseDashboardDate(mixed $value): ?\DateTimeImmutable
    {
        $value = trim((string) $value);
        if ($value === '' || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return null;
        }

        try {
            return new \DateTimeImmutable($value);
        } catch (\Exception) {
            return null;
        }
    }

    /** @param callable(string): bool $hasModule */
    private function recentActivity(User $user, callable $hasModule): array
    {
        $items = [];

        if ($hasModule('expenses') && $this->expenseAccess->canAccess($user)) {
            foreach ($this->expenseService->search($user, ['active' => 'active'], 1, 4)['items'] as $expense) {
                $date = $expense->getCreatedAt() ?? $expense->getExpenseDate();
                $items[] = [
                    'title' => (string) $expense->getTitle(),
                    'meta' => sprintf('%s · %s dh', $expense->getStatusLabel(), number_format((float) $expense->getAmountTtc(), 0, ',', ' ')),
                    'date' => $date,
                    'date_label' => $this->activityDateLabel($date),
                    'icon' => 'bi-receipt',
                    'tone' => 'warning',
                    'route' => 'app_expense_index',
                    'kind' => 'Dépense',
                ];
            }
        }

        if ($hasModule('documents')) {
            foreach ($this->documentService->search($user, '', 1, 4)['items'] as $document) {
                $date = $document->getCreatedAt();
                $items[] = [
                    'title' => (string) $document->getName(),
                    'meta' => (string) ($document->getCategory() ?: $document->getStatus()),
                    'date' => $date,
                    'date_label' => $this->activityDateLabel($date),
                    'icon' => 'bi-file-earmark-text',
                    'tone' => 'info',
                    'route' => 'app_document_index',
                    'kind' => 'Document',
                ];
            }
        }

        if ($hasModule('contacts')) {
            $contacts = $this->contactService->getVisibleContacts($user);
            usort($contacts, static fn ($first, $second): int => ($second->getCreatedAt()?->getTimestamp() ?? 0) <=> ($first->getCreatedAt()?->getTimestamp() ?? 0));
            foreach (array_slice($contacts, 0, 4) as $contact) {
                $date = $contact->getCreatedAt();
                $items[] = [
                    'title' => (string) $contact->getFullName(),
                    'meta' => (string) ($contact->getType() ?: $contact->getCity() ?: 'Contact'),
                    'date' => $date,
                    'date_label' => $this->activityDateLabel($date),
                    'icon' => 'bi-person-lines-fill',
                    'tone' => 'success',
                    'route' => 'app_contact_index',
                    'kind' => 'Contact',
                ];
            }
        }

        if ($hasModule('agenda') && $this->appointmentAccess->canAccess($user)) {
            $viewAll = $this->appointmentAccess->canViewAll($user);
            foreach ($this->appointmentRepository->searchVisible($user, $viewAll, ['active' => 'active'], 1, 4)['items'] as $appointment) {
                $date = $appointment->getCreatedAt() ?? $appointment->getStartAt();
                $items[] = [
                    'title' => (string) $appointment->getTitle(),
                    'meta' => sprintf('%s · %s', $appointment->getStatusLabel(), $appointment->getStartAt()?->format('d/m/Y H:i') ?? 'Non planifié'),
                    'date' => $date,
                    'date_label' => $this->activityDateLabel($date),
                    'icon' => 'bi-calendar2-week',
                    'tone' => 'primary',
                    'route' => 'app_appointment_calendar',
                    'kind' => 'RDV',
                ];
            }
        }

        if ($hasModule('inventory') && $this->inventoryAccess->canAccess($user)) {
            $viewAll = $this->inventoryAccess->canViewAll($user);
            foreach ($this->inventoryMovementRepository->recentVisible($user, $viewAll, 4) as $movement) {
                $date = $movement->getMovementDate();
                $item = $movement->getItem();
                $items[] = [
                    'title' => $item?->getReference().' - '.$item?->getName(),
                    'meta' => sprintf('%s · %d %s', $movement->getTypeLabel(), $movement->getQuantity(), $item?->getUnit() ?? 'piece'),
                    'date' => $date,
                    'date_label' => $this->activityDateLabel($date),
                    'icon' => 'bi-box-seam',
                    'tone' => 'info',
                    'route' => 'app_inventory_dashboard',
                    'kind' => 'Inventaire',
                ];
            }
        }

        usort($items, static fn (array $first, array $second): int => ($second['date']?->getTimestamp() ?? 0) <=> ($first['date']?->getTimestamp() ?? 0));

        return array_slice($items, 0, 7);
    }

    private function activityDateLabel(?\DateTimeImmutable $date): string
    {
        return $date instanceof \DateTimeImmutable ? $date->format('d/m/Y H:i') : 'Date non renseignée';
    }

    /** @return array{available: bool, deleted: int} */
    private function trashOverview(User $user): array
    {
        if (!in_array('ROLE_SUPER_ADMIN', $user->getRoles(), true)) {
            return ['available' => false, 'deleted' => 0];
        }

        return [
            'available' => true,
            'deleted' => $this->trashService->countDeletedItems(),
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

    /** @return array<string, mixed> */
    private function appointmentOverview(User $user, array $period): array
    {
        if (!$this->appointmentAccess->canAccess($user)) {
            return [
                'total' => 0,
                'period_count' => 0,
                'today' => 0,
                'next_7_days' => 0,
                'pending' => 0,
                'high_priority' => 0,
                'upcoming' => [],
                'status_chart' => [],
                'type_chart' => [],
                'priority_chart' => [],
            ];
        }

        $viewAll = $this->appointmentAccess->canViewAll($user);
        $today = new \DateTimeImmutable('today');
        $tomorrow = $today->modify('+1 day');
        $nextWeek = $today->modify('+7 days 23:59:59');

        $total = $this->appointmentRepository->countVisible($user, $viewAll, ['active' => 'active']);
        $periodFilters = [
            'dateFrom' => $period['start']->format('Y-m-d H:i:s'),
            'dateTo' => $period['end']->format('Y-m-d H:i:s'),
            'active' => 'active',
        ];
        $periodCount = $this->appointmentRepository->countVisible($user, $viewAll, $periodFilters);
        $todayCount = $this->appointmentRepository->countVisible($user, $viewAll, [
            'dateFrom' => $today->format('Y-m-d H:i:s'),
            'dateTo' => $tomorrow->modify('-1 second')->format('Y-m-d H:i:s'),
            'active' => 'active',
        ]);
        $nextSevenDays = $this->appointmentRepository->countVisible($user, $viewAll, [
            'dateFrom' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            'dateTo' => $nextWeek->format('Y-m-d H:i:s'),
            'active' => 'active',
        ]);
        $pending = $this->appointmentRepository->countVisible($user, $viewAll, ['status' => 'pending', 'active' => 'active']);
        $highPriority = $this->appointmentRepository->countVisible($user, $viewAll, ['priority' => 'urgent', 'active' => 'active'])
            + $this->appointmentRepository->countVisible($user, $viewAll, ['priority' => 'high', 'active' => 'active']);

        return [
            'total' => $total,
            'period_count' => $periodCount,
            'today' => $todayCount,
            'next_7_days' => $nextSevenDays,
            'pending' => $pending,
            'high_priority' => $highPriority,
            'upcoming' => $this->appointmentAgendaItems($this->appointmentRepository->upcoming($user, $viewAll, [], 6)),
            'status_chart' => $this->appointmentStatusChart($user, $viewAll, $periodFilters),
            'type_chart' => $this->appointmentTypeChart($user, $viewAll, $periodFilters),
            'priority_chart' => $this->appointmentPriorityChart($user, $viewAll, $periodFilters),
        ];
    }

    /**
     * @param list<Appointment> $appointments
     *
     * @return list<array<string, mixed>>
     */
    private function appointmentAgendaItems(array $appointments): array
    {
        return array_map(static function (Appointment $appointment): array {
            $startAt = $appointment->getStartAt();
            $endAt = $appointment->getEndAt();
            $time = $startAt instanceof \DateTimeImmutable ? $startAt->format('d/m H:i') : 'Non planifié';
            if ($endAt instanceof \DateTimeImmutable) {
                $time .= ' - '.$endAt->format('H:i');
            }

            return [
                'title' => (string) $appointment->getTitle(),
                'meta' => sprintf('%s · %s', $time, $appointment->getCustomerName() ?: ($appointment->getLocation() ?: $appointment->getAppointmentTypeLabel())),
                'status' => $appointment->getStatus(),
                'status_label' => $appointment->getStatusLabel(),
            ];
        }, $appointments);
    }

    /** @return list<array<string, mixed>> */
    private function appointmentStatusChart(User $user, bool $viewAll, array $filters = []): array
    {
        $items = [];
        $tones = [
            'planned' => 'primary',
            'confirmed' => 'success',
            'pending' => 'warning',
            'completed' => 'info',
            'cancelled' => 'danger',
            'postponed' => 'secondary',
        ];

        foreach (Appointment::STATUS_LABELS as $status => $label) {
            $items[] = [
                'label' => $label,
                'value' => $this->appointmentRepository->countVisible($user, $viewAll, $filters + ['status' => $status, 'active' => 'active']),
                'tone' => $tones[$status] ?? 'secondary',
            ];
        }

        return $this->withPercentages($items);
    }

    /** @return list<array<string, mixed>> */
    private function appointmentTypeChart(User $user, bool $viewAll, array $filters = []): array
    {
        $items = [];
        $tones = ['primary', 'success', 'warning', 'info', 'secondary', 'danger'];

        foreach (Appointment::TYPE_LABELS as $type => $label) {
            $items[] = [
                'label' => $label,
                'value' => $this->appointmentRepository->countVisible($user, $viewAll, $filters + ['appointmentType' => $type, 'active' => 'active']),
                'tone' => $tones[count($items) % count($tones)],
            ];
        }

        return $this->withPercentages($items);
    }

    /** @return list<array<string, mixed>> */
    private function appointmentPriorityChart(User $user, bool $viewAll, array $filters = []): array
    {
        $items = [];
        $tones = [
            'low' => 'secondary',
            'normal' => 'primary',
            'high' => 'warning',
            'urgent' => 'danger',
        ];

        foreach (Appointment::PRIORITY_LABELS as $priority => $label) {
            $items[] = [
                'label' => $label,
                'value' => $this->appointmentRepository->countVisible($user, $viewAll, $filters + ['priority' => $priority, 'active' => 'active']),
                'tone' => $tones[$priority] ?? 'secondary',
            ];
        }

        return $this->withPercentages($items);
    }

    /** @return array<string, mixed> */
    private function inventoryOverview(User $user): array
    {
        if (!$this->inventoryAccess->canAccess($user)) {
            return [
                'total_items' => 0,
                'active_items' => 0,
                'archived_items' => 0,
                'assigned_items' => 0,
                'unavailable_items' => 0,
                'maintenance_items' => 0,
                'lost_items' => 0,
                'pending_requests' => 0,
                'pending_transfers' => 0,
                'pending_inventories' => 0,
                'recent_movements' => [],
                'status_chart' => [],
                'category_chart' => [],
                'site_chart' => [],
                'logistics_chart' => [],
                'request_chart' => [],
            ];
        }

        $viewAll = $this->inventoryAccess->canViewAll($user);
        $active = $this->inventoryItemRepository->countVisible($user, $viewAll, ['active' => 'active']);
        $archived = $this->inventoryItemRepository->countVisible($user, $viewAll, ['active' => 'archived']);
        $assigned = $this->inventoryItemRepository->countVisible($user, $viewAll, ['active' => 'active', 'status' => 'assigned']);
        $maintenance = $this->inventoryItemRepository->countVisible($user, $viewAll, ['active' => 'active', 'status' => 'maintenance']);
        $lost = $this->inventoryItemRepository->countVisible($user, $viewAll, ['active' => 'active', 'status' => 'lost']);
        $retired = $this->inventoryItemRepository->countVisible($user, $viewAll, ['active' => 'active', 'status' => 'retired']);
        $pendingTransfers = $this->inventoryRequestRepository->countPendingVisibleByType($user, $viewAll, 'transfer');
        $pendingInventories = $this->inventoryRequestRepository->countPendingVisibleByType($user, $viewAll, 'inventory');

        return [
            'total_items' => $active + $archived,
            'active_items' => $active,
            'archived_items' => $archived,
            'assigned_items' => $assigned,
            'unavailable_items' => $maintenance + $lost + $retired,
            'maintenance_items' => $maintenance,
            'lost_items' => $lost,
            'pending_requests' => $pendingTransfers + $pendingInventories,
            'pending_transfers' => $pendingTransfers,
            'pending_inventories' => $pendingInventories,
            'recent_movements' => $this->inventoryMovementRepository->recentVisible($user, $viewAll, 5),
            'status_chart' => $this->inventoryChart($this->labelInventoryStatuses($this->inventoryItemRepository->groupByStatus($user, $viewAll))),
            'category_chart' => $this->inventoryChart($this->inventoryItemRepository->groupByCategory($user, $viewAll)),
            'site_chart' => $this->inventoryChart($this->inventoryItemRepository->groupBySite($user, $viewAll)),
            'logistics_chart' => $this->inventoryChart($this->labelInventoryLogistics($this->inventoryItemRepository->groupByLogisticsStatus($user, $viewAll))),
            'request_chart' => $this->withShares([
                ['label' => 'Transports', 'short_label' => 'Transport', 'value' => $pendingTransfers, 'tone' => 'primary'],
                ['label' => 'Inventaires', 'short_label' => 'Inventaire', 'value' => $pendingInventories, 'tone' => 'warning'],
            ]),
        ];
    }

    /** @param list<array{label: string, value: int}> $rows */
    private function inventoryChart(array $rows): array
    {
        $tones = ['primary', 'success', 'warning', 'info', 'secondary', 'danger'];
        $items = [];

        foreach ($rows as $index => $row) {
            $items[] = [
                'label' => $row['label'],
                'short_label' => mb_substr($row['label'], 0, 12),
                'value' => $row['value'],
                'display' => (string) $row['value'],
                'tone' => $tones[$index % count($tones)],
            ];
        }

        return $this->withShares($items);
    }

    /** @param list<array{label: string, value: int}> $rows */
    private function labelInventoryStatuses(array $rows): array
    {
        $labels = array_flip(InventoryItem::STATUSES);

        return array_map(static fn (array $row): array => [
            'label' => $labels[$row['label']] ?? $row['label'],
            'value' => $row['value'],
        ], $rows);
    }

    /** @param list<array{label: string, value: int}> $rows */
    private function labelInventoryLogistics(array $rows): array
    {
        $labels = array_flip(InventoryItem::LOGISTICS_STATUSES);

        return array_map(static fn (array $row): array => [
            'label' => $labels[$row['label']] ?? $row['label'],
            'value' => $row['value'],
        ], $rows);
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

    /** @return array{month_total: float, period_total: float, period_count: int, period_pending_count: int, pending_count: int, paid_total: float, refused_count: int, validated_count: int, categories: list<array{label: string, total: string}>, category_chart: list<array<string, mixed>>, status_chart: list<array<string, mixed>>, recent: list<Expense>} */
    private function expenseOverview(User $user, array $period): array
    {
        /** @var array{month_total: float, pending_count: int, paid_total: float, refused_count: int, validated_count: int, categories: list<array{label: string, total: string}>} $stats */
        $stats = $this->expenseService->stats($user);
        $admin = $this->expenseAccess->isAdmin($user);
        $periodFilters = [
            'active' => 'active',
            'dateFrom' => $period['start']->format('Y-m-d H:i:s'),
            'dateTo' => $period['end']->format('Y-m-d H:i:s'),
        ];
        $periodCategories = $this->expenseRepository->totalsByCategory($user, $admin, $periodFilters);

        $stats['period_total'] = $this->expenseRepository->sumVisible($user, $admin, $periodFilters);
        $stats['period_count'] = $this->expenseRepository->countVisible($user, $admin, $periodFilters);
        $stats['period_pending_count'] = $this->expenseRepository->countVisible($user, $admin, $periodFilters + ['status' => Expense::STATUS_PENDING]);
        $stats['categories'] = $periodCategories;
        $stats['category_chart'] = $this->expenseCategoryChart($stats['categories']);
        $stats['status_chart'] = $this->expenseStatusChart($user, $admin, $periodFilters);
        $stats['recent'] = $this->expenseRepository->searchVisible($user, $admin, ['active' => 'active'], 1, 5);

        return $stats;
    }

    /** @param array<string, mixed> $filters */
    private function expenseStatusChart(User $user, bool $admin, array $filters = []): array
    {
        $tones = [
            Expense::STATUS_DRAFT => 'secondary',
            Expense::STATUS_PENDING => 'warning',
            Expense::STATUS_VALIDATED => 'primary',
            Expense::STATUS_REFUSED => 'danger',
            Expense::STATUS_PAID => 'success',
            Expense::STATUS_CANCELLED => 'dark',
        ];
        $items = [];

        foreach (Expense::STATUS_LABELS as $status => $label) {
            $items[] = [
                'label' => $label,
                'short_label' => mb_substr($label, 0, 12),
                'value' => $this->expenseRepository->countVisible($user, $admin, $filters + ['status' => $status, 'active' => 'active']),
                'tone' => $tones[$status] ?? 'secondary',
            ];
        }

        return $this->withPercentages($items);
    }

    /** @return array{total: int, active: int, inactive: int} */
    private function usersOverview(): array
    {
        $total = $this->userRepository->count([]);
        $active = $this->userRepository->count(['isActive' => true]);

        return ['total' => $total, 'active' => $active, 'inactive' => max(0, $total - $active)];
    }

    /**
     * @param list<array{label: string, value: int, tone: string}> $items
     *
     * @return list<array{label: string, value: int, tone: string, percent: int}>
     */
    private function withPercentages(array $items): array
    {
        $max = max(1, ...array_map(static fn (array $item): int => $item['value'], $items));
        $total = max(1, array_sum(array_map(static fn (array $item): int => $item['value'], $items)));
        $offset = 0;
        $chart = [];

        foreach ($items as $item) {
            $share = $item['value'] > 0 ? (int) round(($item['value'] / $total) * 100) : 0;
            $chart[] = $item + [
                'percent' => $item['value'] > 0 ? max(8, (int) round(($item['value'] / $max) * 100)) : 0,
                'share' => $share,
                'offset' => $offset,
            ];
            $offset += $share;
        }

        return $chart;
    }

    /**
     * @param list<array<string, mixed>> $items
     *
     * @return list<array<string, mixed>>
     */
    private function withShares(array $items): array
    {
        if ($items === []) {
            return [];
        }

        $max = max(1, ...array_map(static fn (array $item): int => (int) $item['value'], $items));
        $total = max(1, array_sum(array_map(static fn (array $item): int => (int) $item['value'], $items)));
        $offset = 0;
        $chart = [];

        foreach ($items as $item) {
            $value = (int) $item['value'];
            $share = $value > 0 ? (int) round(($value / $total) * 100) : 0;
            $chart[] = $item + [
                'percent' => $value > 0 ? max(8, (int) round(($value / $max) * 100)) : 0,
                'share' => $share,
                'offset' => $offset,
            ];
            $offset += $share;
        }

        return $chart;
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
                'display' => number_format($amount, 0, ',', ' ').' dh',
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
    private function dashboardAlerts(array $expiringContracts, int $pendingPasswords, int $inactiveUsers, int $pendingExpenses, int $pendingAppointments, int $priorityAppointments, array $inventory): array
    {
        $alerts = array_map(static fn ($contract): array => [
            'title' => (string) $contract->getReference(),
            'text' => sprintf('%s arrive à échéance le %s', $contract->getCustomerName(), $contract->getEndDate()?->format('d/m/Y') ?? '-'),
            'icon' => 'bi-calendar-event',
            'tone' => 'warning',
        ], $expiringContracts);

        if ($pendingAppointments > 0) {
            $alerts[] = [
                'title' => 'Rendez-vous en attente',
                'text' => sprintf('%d rendez-vous à confirmer ou traiter.', $pendingAppointments),
                'icon' => 'bi-calendar2-week',
                'tone' => 'warning',
            ];
        }

        if ($priorityAppointments > 0) {
            $alerts[] = [
                'title' => 'Priorité agenda',
                'text' => sprintf('%d rendez-vous haute priorité ou urgent.', $priorityAppointments),
                'icon' => 'bi-exclamation-triangle',
                'tone' => 'danger',
            ];
        }

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

        if ($inventory['pending_requests'] > 0) {
            $alerts[] = [
                'title' => 'Inventaire à valider',
                'text' => sprintf('%d demande%s en attente : %d transport%s, %d inventaire%s.', $inventory['pending_requests'], $inventory['pending_requests'] > 1 ? 's' : '', $inventory['pending_transfers'], $inventory['pending_transfers'] > 1 ? 's' : '', $inventory['pending_inventories'], $inventory['pending_inventories'] > 1 ? 's' : ''),
                'icon' => 'bi-box-seam',
                'tone' => 'info',
            ];
        }

        if ($inventory['unavailable_items'] > 0) {
            $alerts[] = [
                'title' => 'Matériel à surveiller',
                'text' => sprintf('%d matériel%s en maintenance, perdu%s ou sorti%s du parc.', $inventory['unavailable_items'], $inventory['unavailable_items'] > 1 ? 's' : '', $inventory['unavailable_items'] > 1 ? 's' : '', $inventory['unavailable_items'] > 1 ? 's' : ''),
                'icon' => 'bi-exclamation-triangle',
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
        $stats = $this->expenseOverview($user, $this->dashboardPeriod([]));
        $categoryItems = array_map(static fn (array $item): array => [
            'title' => $item['label'],
            'text' => number_format((float) $item['total'], 2, ',', ' ').' dh TTC',
        ], array_slice($stats['categories'], 0, 3));

        return [
            'slug' => 'expenses',
            'name' => 'Dépenses',
            'icon' => 'bi-cash-coin',
            'route' => $module->getRouteName(),
            'tone' => 'warning',
            'headline' => sprintf('%s dh ce mois-ci', number_format($stats['month_total'], 2, ',', ' ')),
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

    /** @return array<string, mixed> */
    private function agendaSection(AppModule $module, User $user): array
    {
        $agenda = $this->appointmentOverview($user, $this->dashboardPeriod([]));

        return [
            'slug' => 'agenda',
            'name' => 'Agenda - RDV',
            'icon' => 'bi-calendar2-week',
            'route' => $module->getRouteName(),
            'tone' => 'primary',
            'headline' => sprintf('%d rendez-vous visibles', $agenda['total']),
            'metrics' => [
                ['label' => 'Aujourd’hui', 'value' => $agenda['today'], 'icon' => 'bi-calendar2-check'],
                ['label' => '7 jours', 'value' => $agenda['next_7_days'], 'icon' => 'bi-calendar-week'],
                ['label' => 'En attente', 'value' => $agenda['pending'], 'icon' => 'bi-hourglass-split'],
                ['label' => 'Priorité haute', 'value' => $agenda['high_priority'], 'icon' => 'bi-exclamation-triangle'],
            ],
            'alerts' => $agenda['pending'] > 0 ? [[
                'title' => 'Rendez-vous à confirmer',
                'text' => sprintf('%d rendez-vous en attente.', $agenda['pending']),
                'level' => 'warning',
            ]] : [],
            'items_title' => 'Prochains rendez-vous',
            'items' => array_map(static fn (array $item): array => [
                'title' => $item['title'],
                'text' => $item['meta'],
            ], array_slice($agenda['upcoming'], 0, 3)),
            'progress' => $agenda['total'] > 0 ? min(100, (int) round(($agenda['next_7_days'] / max(1, $agenda['total'])) * 100)) : 0,
            'progress_label' => 'Charge 7 jours',
            'chart' => $agenda['status_chart'],
            'chart_type' => 'bars',
        ];
    }

    /** @return array<string, mixed> */
    private function inventorySection(AppModule $module, User $user): array
    {
        $inventory = $this->inventoryOverview($user);
        $siteItems = array_map(static fn (array $item): array => [
            'title' => $item['label'],
            'text' => sprintf('%d matériel%s', $item['value'], $item['value'] > 1 ? 's' : ''),
        ], array_slice($inventory['site_chart'], 0, 3));

        return [
            'slug' => 'inventory',
            'name' => 'Inventaire',
            'icon' => 'bi-box-seam',
            'route' => $module->getRouteName(),
            'tone' => $inventory['pending_requests'] > 0 ? 'warning' : 'primary',
            'headline' => sprintf('%d matériel%s actif%s', $inventory['active_items'], $inventory['active_items'] > 1 ? 's' : '', $inventory['active_items'] > 1 ? 's' : ''),
            'metrics' => [
                ['label' => 'Matériels actifs', 'value' => $inventory['active_items'], 'icon' => 'bi-box-seam'],
                ['label' => 'Affectés', 'value' => $inventory['assigned_items'], 'icon' => 'bi-person-check'],
                ['label' => 'À surveiller', 'value' => $inventory['unavailable_items'], 'icon' => 'bi-exclamation-triangle'],
                ['label' => 'Demandes', 'value' => $inventory['pending_requests'], 'icon' => 'bi-clipboard-check'],
            ],
            'alerts' => $inventory['pending_requests'] > 0 ? [[
                'title' => 'Validation requise',
                'text' => sprintf('%d demande%s inventaire/transport à traiter.', $inventory['pending_requests'], $inventory['pending_requests'] > 1 ? 's' : ''),
                'level' => 'warning',
            ]] : [],
            'items_title' => 'Sites principaux',
            'items' => $siteItems,
            'progress' => $inventory['active_items'] > 0 ? min(100, (int) round(($inventory['unavailable_items'] / max(1, $inventory['active_items'])) * 100)) : 0,
            'progress_label' => 'Matériel à surveiller',
            'chart' => $inventory['status_chart'],
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
            'progress_label' => 'Configuration',
        ];
    }
}
