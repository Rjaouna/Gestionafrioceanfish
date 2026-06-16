<?php

namespace App\Controller\Maintenance;

use App\Entity\Intervenant;
use App\Entity\User;
use App\Form\IntervenantType;
use App\Security\Voter\IntervenantVoter;
use App\Security\Voter\ModuleAccessVoter;
use App\Service\JsonResponder;
use App\Service\Maintenance\IntervenantService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/maintenance/intervenants')]
#[IsGranted('ROLE_USER')]
final class IntervenantController extends AbstractController
{
    public function __construct(
        private readonly IntervenantService $intervenantService,
        private readonly JsonResponder $jsonResponder,
    ) {
    }

    #[Route('', name: 'app_maintenance_intervenant_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $this->denyAccessUnlessGranted(ModuleAccessVoter::ACCESS, 'maintenance');

        return $this->render('maintenance/intervenant/index.html.twig', [
            'intervenants' => $this->intervenantService->search((string) $request->query->get('q', ''), $this->currentUser()),
            'create_form' => $this->buildForm(new Intervenant(), 'app_maintenance_intervenant_create'),
        ]);
    }

    #[Route('/recherche', name: 'app_maintenance_intervenant_search', methods: ['GET'])]
    public function search(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(ModuleAccessVoter::ACCESS, 'maintenance');
        $intervenants = $this->intervenantService->search((string) $request->query->get('q', ''), $this->currentUser());

        return $this->jsonResponder->success('Recherche mise à jour.', [
            'html' => $this->renderView('maintenance/intervenant/_grid.html.twig', [
                'intervenants' => $intervenants,
            ]),
            'count' => count($intervenants),
        ]);
    }

    #[Route('/creer', name: 'app_maintenance_intervenant_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(IntervenantVoter::CREATE);
        $intervenant = new Intervenant();
        $form = $this->buildForm($intervenant, 'app_maintenance_intervenant_create');
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->jsonResponder->invalidForm($form);
        }

        $this->intervenantService->create($intervenant, $this->currentUser());

        return $this->jsonResponder->success('L’intervenant a été créé.', ['reload' => true], 201);
    }

    #[Route('/{id}/formulaire', name: 'app_maintenance_intervenant_form', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function form(Intervenant $intervenant): Response
    {
        $this->denyAccessUnlessGranted(IntervenantVoter::EDIT, $intervenant);

        return $this->render('maintenance/intervenant/_form_modal.html.twig', [
            'form' => $this->buildForm($intervenant, 'app_maintenance_intervenant_edit', ['id' => $intervenant->getId()]),
            'title' => sprintf('Modifier %s', $intervenant->getDisplayName()),
            'submit_label' => 'Enregistrer',
        ]);
    }

    #[Route('/{id}/modifier', name: 'app_maintenance_intervenant_edit', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function edit(Intervenant $intervenant, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(IntervenantVoter::EDIT, $intervenant);
        $form = $this->buildForm($intervenant, 'app_maintenance_intervenant_edit', ['id' => $intervenant->getId()]);
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->jsonResponder->invalidForm($form);
        }

        $this->intervenantService->update($intervenant, $this->currentUser());

        return $this->jsonResponder->success('L’intervenant a été modifié.', ['reload' => true]);
    }

    #[Route('/{id}/statut', name: 'app_maintenance_intervenant_toggle', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function toggle(Intervenant $intervenant, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(IntervenantVoter::ARCHIVE, $intervenant);
        $payload = $request->toArray();
        $this->assertCsrf((string) ($payload['token'] ?? ''), 'toggle_maintenance_intervenant_'.$intervenant->getId());
        $active = $this->intervenantService->toggle($intervenant, $this->currentUser());

        return $this->jsonResponder->success(
            $active ? 'L’intervenant a été réactivé.' : 'L’intervenant a été désactivé.',
            ['reload' => true],
        );
    }

    #[Route('/{id}', name: 'app_maintenance_intervenant_delete', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    public function delete(Intervenant $intervenant, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(IntervenantVoter::DELETE, $intervenant);
        $payload = $request->toArray();
        $this->assertCsrf((string) ($payload['token'] ?? ''), 'delete_maintenance_intervenant_'.$intervenant->getId());
        $this->intervenantService->delete($intervenant, $this->currentUser());

        return $this->jsonResponder->success('L’intervenant a été supprimé.', ['reload' => true]);
    }

    /** @param array<string, int|string|null> $parameters */
    private function buildForm(Intervenant $intervenant, string $route, array $parameters = []): \Symfony\Component\Form\FormInterface
    {
        return $this->createForm(IntervenantType::class, $intervenant, [
            'action' => $this->generateUrl($route, $parameters),
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
