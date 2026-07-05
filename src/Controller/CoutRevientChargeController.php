<?php

namespace App\Controller;

use App\Entity\CoutRevientChargeConfig;
use App\Entity\User;
use App\Form\CoutRevientChargeConfigType;
use App\Security\Voter\ModuleAccessVoter;
use App\Service\CoutRevient\CoutRevientChargeConfigService;
use App\Service\JsonResponder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/cout-revient/charges')]
#[IsGranted('ROLE_USER')]
final class CoutRevientChargeController extends AbstractController
{
    public function __construct(
        private readonly CoutRevientChargeConfigService $chargeConfigService,
        private readonly JsonResponder $jsonResponder,
    ) {
    }

    #[Route('', name: 'app_cout_revient_charge_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $this->denyAccessUnlessGranted(ModuleAccessVoter::ACCESS, 'cout-revient');

        return $this->render('cout_revient/charge_config/index.html.twig', [
            'charges' => $this->chargeConfigService->search($this->currentUser(), (string) $request->query->get('q', '')),
            'create_form' => $this->buildForm(new CoutRevientChargeConfig(), 'app_cout_revient_charge_create'),
            'query' => (string) $request->query->get('q', ''),
        ]);
    }

    #[Route('/creer', name: 'app_cout_revient_charge_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(ModuleAccessVoter::ACCESS, 'cout-revient');
        $config = new CoutRevientChargeConfig();
        $form = $this->buildForm($config, 'app_cout_revient_charge_create');
        $form->handleRequest($request);
        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->jsonResponder->invalidForm($form);
        }

        $this->chargeConfigService->create($config, $this->currentUser());

        return $this->jsonResponder->success('Charge configuree.', ['reload' => true], 201);
    }

    #[Route('/{id}/formulaire', name: 'app_cout_revient_charge_form', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function form(CoutRevientChargeConfig $config): Response
    {
        $this->denyAccessUnlessGranted(ModuleAccessVoter::ACCESS, 'cout-revient');

        return $this->render('cout_revient/charge_config/_form_modal.html.twig', [
            'form' => $this->buildForm($config, 'app_cout_revient_charge_update', ['id' => $config->getId()]),
            'title' => 'Modifier une charge',
            'submit_label' => 'Enregistrer',
        ]);
    }

    #[Route('/{id}/modifier', name: 'app_cout_revient_charge_update', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function update(CoutRevientChargeConfig $config, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(ModuleAccessVoter::ACCESS, 'cout-revient');
        $form = $this->buildForm($config, 'app_cout_revient_charge_update', ['id' => $config->getId()]);
        $form->handleRequest($request);
        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->jsonResponder->invalidForm($form);
        }

        $this->chargeConfigService->update($config, $this->currentUser());

        return $this->jsonResponder->success('Charge mise a jour.', ['reload' => true]);
    }

    #[Route('/{id}/statut', name: 'app_cout_revient_charge_toggle', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function toggle(CoutRevientChargeConfig $config, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(ModuleAccessVoter::ACCESS, 'cout-revient');
        $payload = $request->toArray();
        if (!$this->isCsrfTokenValid('toggle_cout_charge_'.$config->getId(), (string) ($payload['token'] ?? ''))) {
            throw new \DomainException('Jeton de sécurité invalide. Rechargez la page.');
        }

        $active = $this->chargeConfigService->toggle($config, $this->currentUser());

        return $this->jsonResponder->success(
            $active ? 'Charge activee.' : 'Charge desactivee.',
            ['reload' => true],
        );
    }

    /** @param array<string, int|string|null> $parameters */
    private function buildForm(CoutRevientChargeConfig $config, string $route, array $parameters = []): FormInterface
    {
        return $this->createForm(CoutRevientChargeConfigType::class, $config, [
            'action' => $this->generateUrl($route, $parameters),
        ]);
    }

    private function currentUser(): User
    {
        $user = $this->getUser();
        \assert($user instanceof User);

        return $user;
    }
}
