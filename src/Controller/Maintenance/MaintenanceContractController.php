<?php

namespace App\Controller\Maintenance;

use App\Entity\MaintenanceContract;
use App\Entity\User;
use App\Form\MaintenanceContractType;
use App\Security\Voter\MaintenanceContractVoter;
use App\Security\Voter\ModuleAccessVoter;
use App\Service\JsonResponder;
use App\Service\Maintenance\IntervenantService;
use App\Service\Maintenance\MaintenanceContractService;
use App\Service\Maintenance\MaintenanceShareService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/maintenance/contrats')]
#[IsGranted('ROLE_USER')]
final class MaintenanceContractController extends AbstractController
{
    public function __construct(
        private readonly MaintenanceContractService $contractService,
        private readonly IntervenantService $intervenantService,
        private readonly MaintenanceShareService $shareService,
        private readonly JsonResponder $jsonResponder,
    ) {
    }

    #[Route('', name: 'app_maintenance_contract_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $this->denyAccessUnlessGranted(ModuleAccessVoter::ACCESS, 'maintenance');

        $contracts = $this->contractService->search((string) $request->query->get('q', ''), $request->query->get('status') ?: null, $this->currentUser());

        return $this->render('maintenance/contract/index.html.twig', [
            'contracts' => $contracts,
            'maintenance_share_counts' => $this->shareService->countActiveShares(MaintenanceShareService::TYPE_CONTRACT, $contracts),
            'create_form' => $this->buildForm(
                (new MaintenanceContract())->setReference($this->contractService->nextReference()),
                'app_maintenance_contract_create',
            ),
            'available_intervenants' => $this->intervenantService->available($this->currentUser()),
            'contract_type_suggestions' => $this->contractService->contractTypeSuggestions($this->currentUser()),
            'selected_status' => (string) $request->query->get('status', ''),
        ]);
    }

    #[Route('/recherche', name: 'app_maintenance_contract_search', methods: ['GET'])]
    public function search(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(ModuleAccessVoter::ACCESS, 'maintenance');
        $contracts = $this->contractService->search((string) $request->query->get('q', ''), $request->query->get('status') ?: null, $this->currentUser());

        return $this->jsonResponder->success('Recherche mise à jour.', [
            'html' => $this->renderView('maintenance/contract/_grid.html.twig', [
                'contracts' => $contracts,
                'maintenance_share_counts' => $this->shareService->countActiveShares(MaintenanceShareService::TYPE_CONTRACT, $contracts),
            ]),
            'count' => count($contracts),
        ]);
    }

    #[Route('/creer', name: 'app_maintenance_contract_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(MaintenanceContractVoter::CREATE);
        $contract = (new MaintenanceContract())->setReference($this->contractService->nextReference());
        $form = $this->buildForm($contract, 'app_maintenance_contract_create');
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->jsonResponder->invalidForm($form);
        }

        try {
            $this->contractService->create($contract, $this->currentUser());
        } catch (\DomainException $exception) {
            return $this->jsonResponder->error($exception->getMessage(), [], 422);
        }

        return $this->jsonResponder->success('Le contrat a été créé.', ['reload' => true], 201);
    }

    #[Route('/{id}/consulter', name: 'app_maintenance_contract_view', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function view(MaintenanceContract $contract, Request $request): Response
    {
        $this->denyAccessUnlessGranted(MaintenanceContractVoter::VIEW, $contract);

        if (!$request->isXmlHttpRequest()) {
            return $this->render('maintenance/contract/show.html.twig', ['contract' => $contract]);
        }

        return $this->render('maintenance/contract/_details_modal.html.twig', ['contract' => $contract]);
    }

    #[Route('/{id}/formulaire', name: 'app_maintenance_contract_form', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function form(MaintenanceContract $contract): Response
    {
        $this->denyAccessUnlessGranted(MaintenanceContractVoter::EDIT, $contract);

        return $this->render('maintenance/contract/_form_modal.html.twig', [
            'form' => $this->buildForm($contract, 'app_maintenance_contract_edit', ['id' => $contract->getId()]),
            'title' => sprintf('Modifier %s', $contract->getReference()),
            'submit_label' => 'Enregistrer',
            'available_intervenants' => $this->intervenantService->available($this->currentUser()),
            'contract_type_suggestions' => $this->contractService->contractTypeSuggestions($this->currentUser()),
        ]);
    }

    #[Route('/{id}/modifier', name: 'app_maintenance_contract_edit', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function edit(MaintenanceContract $contract, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(MaintenanceContractVoter::EDIT, $contract);
        $form = $this->buildForm($contract, 'app_maintenance_contract_edit', ['id' => $contract->getId()]);
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->jsonResponder->invalidForm($form);
        }

        try {
            $this->contractService->update($contract, $this->currentUser());
        } catch (\DomainException $exception) {
            return $this->jsonResponder->error($exception->getMessage(), [], 422);
        }

        return $this->jsonResponder->success('Le contrat a été modifié.', ['reload' => true]);
    }

    #[Route('/{id}/archive', name: 'app_maintenance_contract_archive', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function archive(MaintenanceContract $contract, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(MaintenanceContractVoter::ARCHIVE, $contract);
        $payload = $request->toArray();
        $this->assertCsrf((string) ($payload['token'] ?? ''), 'archive_maintenance_contract_'.$contract->getId());
        $active = $this->contractService->archive($contract, $this->currentUser());

        return $this->jsonResponder->success(
            $active ? 'Le contrat a été désarchivé.' : 'Le contrat a été archivé.',
            ['reload' => true],
        );
    }

    #[Route('/{id}', name: 'app_maintenance_contract_delete', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    public function delete(MaintenanceContract $contract, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(MaintenanceContractVoter::DELETE, $contract);
        $payload = $request->toArray();
        $this->assertCsrf((string) ($payload['token'] ?? ''), 'delete_maintenance_contract_'.$contract->getId());
        $movedToTrash = $this->contractService->delete($contract, $this->currentUser());
        if ($movedToTrash) {
            return $this->jsonResponder->success('Le contrat a ete deplace dans la corbeille.', ['reload' => true]);
        }

        return $this->jsonResponder->success('Le contrat a été supprimé.', ['reload' => true]);
    }

    /** @param array<string, int|string|null> $parameters */
    private function buildForm(MaintenanceContract $contract, string $route, array $parameters = []): \Symfony\Component\Form\FormInterface
    {
        $visibleIntervenantIds = array_values(array_filter(array_map(
            static fn ($intervenant): ?int => $intervenant->getId(),
            $this->intervenantService->available($this->currentUser()),
        )));

        return $this->createForm(MaintenanceContractType::class, $contract, [
            'action' => $this->generateUrl($route, $parameters),
            'visible_intervenant_ids' => $visibleIntervenantIds,
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
