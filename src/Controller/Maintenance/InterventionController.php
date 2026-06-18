<?php

namespace App\Controller\Maintenance;

use App\Entity\Intervention;
use App\Entity\InterventionIntervenant;
use App\Entity\User;
use App\Form\InterventionType;
use App\Repository\InterventionIntervenantRepository;
use App\Security\Voter\InterventionVoter;
use App\Security\Voter\ModuleAccessVoter;
use App\Service\JsonResponder;
use App\Service\Maintenance\IntervenantService;
use App\Service\Maintenance\InterventionHistoryService;
use App\Service\Maintenance\InterventionService;
use App\Service\Maintenance\MaintenanceContractService;
use App\Service\Maintenance\MaintenanceShareService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/maintenance/interventions')]
#[IsGranted('ROLE_USER')]
final class InterventionController extends AbstractController
{
    public function __construct(
        private readonly InterventionService $interventionService,
        private readonly IntervenantService $intervenantService,
        private readonly MaintenanceContractService $contractService,
        private readonly InterventionHistoryService $historyService,
        private readonly InterventionIntervenantRepository $assignmentRepository,
        private readonly MaintenanceShareService $shareService,
        private readonly JsonResponder $jsonResponder,
    ) {
    }

    #[Route('', name: 'app_maintenance_intervention_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $this->denyAccessUnlessGranted(ModuleAccessVoter::ACCESS, 'maintenance');

        $interventions = $this->interventionService->search((string) $request->query->get('q', ''), $request->query->get('status') ?: null, $this->currentUser());

        return $this->render('maintenance/intervention/index.html.twig', [
            'interventions' => $interventions,
            'maintenance_share_counts' => $this->shareService->countActiveShares(MaintenanceShareService::TYPE_INTERVENTION, $interventions),
            'create_form' => $this->buildForm(
                (new Intervention())->setReference($this->interventionService->nextReference()),
                'app_maintenance_intervention_create',
            ),
            'available_intervenants' => $this->intervenantService->available($this->currentUser()),
            'selected_status' => (string) $request->query->get('status', ''),
        ]);
    }

    #[Route('/recherche', name: 'app_maintenance_intervention_search', methods: ['GET'])]
    public function search(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(ModuleAccessVoter::ACCESS, 'maintenance');
        $interventions = $this->interventionService->search((string) $request->query->get('q', ''), $request->query->get('status') ?: null, $this->currentUser());

        return $this->jsonResponder->success('Recherche mise à jour.', [
            'html' => $this->renderView('maintenance/intervention/_grid.html.twig', [
                'interventions' => $interventions,
                'maintenance_share_counts' => $this->shareService->countActiveShares(MaintenanceShareService::TYPE_INTERVENTION, $interventions),
            ]),
            'count' => count($interventions),
        ]);
    }

    #[Route('/creer', name: 'app_maintenance_intervention_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(InterventionVoter::CREATE);
        $intervention = (new Intervention())->setReference($this->interventionService->nextReference());
        $form = $this->buildForm($intervention, 'app_maintenance_intervention_create');
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->jsonResponder->invalidForm($form);
        }

        try {
            $this->interventionService->create($intervention, $this->currentUser());
        } catch (\DomainException $exception) {
            return $this->jsonResponder->error($exception->getMessage(), [], 422);
        }

        return $this->jsonResponder->success('L’intervention a été créée.', ['reload' => true], 201);
    }

    #[Route('/{id}/consulter', name: 'app_maintenance_intervention_view', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function view(Intervention $intervention, Request $request): Response
    {
        $this->denyAccessUnlessGranted(InterventionVoter::VIEW, $intervention);
        $parameters = [
            'intervention' => $intervention,
            'history' => $this->historyService->getHistory($intervention),
        ];

        if (!$request->isXmlHttpRequest()) {
            return $this->render('maintenance/intervention/show.html.twig', $parameters);
        }

        return $this->render('maintenance/intervention/_details_modal.html.twig', $parameters);
    }

    #[Route('/{id}/formulaire', name: 'app_maintenance_intervention_form', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function form(Intervention $intervention): Response
    {
        $this->denyAccessUnlessGranted(InterventionVoter::EDIT, $intervention);

        return $this->render('maintenance/intervention/_form_modal.html.twig', [
            'form' => $this->buildForm($intervention, 'app_maintenance_intervention_edit', ['id' => $intervention->getId()]),
            'title' => sprintf('Modifier %s', $intervention->getReference()),
            'submit_label' => 'Enregistrer',
            'available_intervenants' => $this->intervenantService->available($this->currentUser()),
        ]);
    }

    #[Route('/{id}/modifier', name: 'app_maintenance_intervention_edit', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function edit(Intervention $intervention, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(InterventionVoter::EDIT, $intervention);
        $form = $this->buildForm($intervention, 'app_maintenance_intervention_edit', ['id' => $intervention->getId()]);
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->jsonResponder->invalidForm($form);
        }

        try {
            $this->interventionService->update($intervention, $this->currentUser());
        } catch (\DomainException $exception) {
            return $this->jsonResponder->error($exception->getMessage(), [], 422);
        }

        return $this->jsonResponder->success('L’intervention a été modifiée.', ['reload' => true]);
    }

    #[Route('/{id}/demarrer', name: 'app_maintenance_intervention_start', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function start(Intervention $intervention, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(InterventionVoter::CHANGE_STATUS, $intervention);
        $payload = $request->toArray();
        $this->assertCsrf((string) ($payload['token'] ?? ''), 'start_maintenance_intervention_'.$intervention->getId());

        try {
            $this->interventionService->start($intervention, $this->currentUser());
        } catch (\DomainException $exception) {
            return $this->jsonResponder->error($exception->getMessage(), [], 422);
        }

        return $this->jsonResponder->success('L’intervention a démarré.', ['reload' => true]);
    }

    #[Route('/{id}/statut', name: 'app_maintenance_intervention_status', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function status(Intervention $intervention, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(InterventionVoter::CHANGE_STATUS, $intervention);
        $payload = $request->toArray();
        $this->assertCsrf((string) ($payload['token'] ?? ''), 'status_maintenance_intervention_'.$intervention->getId());

        try {
            $this->interventionService->changeStatus(
                $intervention,
                (string) ($payload['status'] ?? ''),
                $this->currentUser(),
                isset($payload['comment']) ? (string) $payload['comment'] : null,
            );
        } catch (\DomainException $exception) {
            return $this->jsonResponder->error($exception->getMessage(), [], 422);
        }

        return $this->jsonResponder->success('Le statut a été mis à jour.', ['reload' => true]);
    }

    #[Route('/{id}/cloturer', name: 'app_maintenance_intervention_close', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function close(Intervention $intervention, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(InterventionVoter::CLOSE, $intervention);
        $payload = $request->request->all();
        $this->assertCsrf((string) ($payload['token'] ?? ''), 'close_maintenance_intervention_'.$intervention->getId());
        try {
            $this->interventionService->close(
                $intervention,
                (string) ($payload['workDone'] ?? ''),
                isset($payload['resultStatus']) ? (string) $payload['resultStatus'] : null,
                isset($payload['comment']) ? (string) $payload['comment'] : null,
                $this->currentUser(),
            );
        } catch (\DomainException $exception) {
            return $this->jsonResponder->error($exception->getMessage(), [], 422);
        }

        return $this->jsonResponder->success('L’intervention est terminée.', ['reload' => true]);
    }

    #[Route('/{id}/affectations', name: 'app_maintenance_intervention_assign_modal', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function assignModal(Intervention $intervention): Response
    {
        $this->denyAccessUnlessGranted(InterventionVoter::ASSIGN_INTERVENANT, $intervention);

        return $this->render('maintenance/intervention/_assign_intervenant_modal.html.twig', [
            'intervention' => $intervention,
            'intervenants' => $this->intervenantService->available($this->currentUser()),
        ]);
    }

    #[Route('/{id}/affectations', name: 'app_maintenance_intervention_assign', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function assign(Intervention $intervention, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(InterventionVoter::ASSIGN_INTERVENANT, $intervention);
        $payload = $request->request->all();
        $this->assertCsrf((string) ($payload['token'] ?? ''), 'assign_maintenance_intervention_'.$intervention->getId());

        try {
            $this->interventionService->assignIntervenant(
                $intervention,
                (int) ($payload['intervenantId'] ?? 0),
                isset($payload['roleOnIntervention']) ? (string) $payload['roleOnIntervention'] : null,
                filter_var($payload['isMainIntervenant'] ?? false, FILTER_VALIDATE_BOOL),
                $this->currentUser(),
            );
        } catch (\DomainException $exception) {
            return $this->jsonResponder->error($exception->getMessage(), [], 422);
        }

        return $this->jsonResponder->success('L’intervenant a été affecté.', ['reload' => true]);
    }

    #[Route('/{id}/affectations/{assignmentId}', name: 'app_maintenance_intervention_remove_assignment', requirements: ['id' => '\d+', 'assignmentId' => '\d+'], methods: ['DELETE'])]
    public function removeAssignment(Intervention $intervention, int $assignmentId, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(InterventionVoter::ASSIGN_INTERVENANT, $intervention);
        $payload = $request->toArray();
        $this->assertCsrf((string) ($payload['token'] ?? ''), 'remove_maintenance_assignment_'.$intervention->getId());
        $assignment = $this->assignmentRepository->find($assignmentId);
        if (!$assignment instanceof InterventionIntervenant || $assignment->getIntervention()?->getId() !== $intervention->getId()) {
            return $this->jsonResponder->error('Cette affectation est introuvable.', [], 404);
        }

        $this->interventionService->removeAssignment($assignment, $this->currentUser());

        return $this->jsonResponder->success('L’affectation a été retirée.', ['reload' => true]);
    }

    #[Route('/{id}/archive', name: 'app_maintenance_intervention_archive', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function archive(Intervention $intervention, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(InterventionVoter::ARCHIVE, $intervention);
        $payload = $request->toArray();
        $this->assertCsrf((string) ($payload['token'] ?? ''), 'archive_maintenance_intervention_'.$intervention->getId());
        $active = $this->interventionService->archive($intervention, $this->currentUser());

        return $this->jsonResponder->success(
            $active ? 'L’intervention a été désarchivée.' : 'L’intervention a été archivée.',
            ['reload' => true],
        );
    }

    #[Route('/{id}', name: 'app_maintenance_intervention_delete', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    public function delete(Intervention $intervention, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(InterventionVoter::DELETE, $intervention);
        $payload = $request->toArray();
        $this->assertCsrf((string) ($payload['token'] ?? ''), 'delete_maintenance_intervention_'.$intervention->getId());
        $movedToTrash = $this->interventionService->delete($intervention, $this->currentUser());
        if ($movedToTrash) {
            return $this->jsonResponder->success('L’intervention a été déplacée dans la corbeille.', ['reload' => true]);
        }

        return $this->jsonResponder->success('L’intervention a été supprimée.', ['reload' => true]);
    }

    /** @param array<string, int|string|null> $parameters */
    private function buildForm(Intervention $intervention, string $route, array $parameters = []): \Symfony\Component\Form\FormInterface
    {
        $visibleIntervenantIds = array_values(array_filter(array_map(
            static fn ($intervenant): ?int => $intervenant->getId(),
            $this->intervenantService->available($this->currentUser()),
        )));
        $visibleContractIds = array_values(array_filter(array_map(
            static fn ($contract): ?int => $contract->getId(),
            $this->contractService->activeContracts($this->currentUser()),
        )));

        return $this->createForm(InterventionType::class, $intervention, [
            'action' => $this->generateUrl($route, $parameters),
            'visible_intervenant_ids' => $visibleIntervenantIds,
            'visible_contract_ids' => $visibleContractIds,
        ]);
    }

    private function assertCsrf(string $token, string $id): void
    {
        if (!$this->isCsrfTokenValid($id, $token)) {
            throw new \DomainException('Jeton de sécurité invalide. Rechargez la page.');
        }
    }

    private function currentUser(): User
    {
        $user = $this->getUser();
        \assert($user instanceof User);

        return $user;
    }
}
