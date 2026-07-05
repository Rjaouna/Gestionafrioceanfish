<?php

namespace App\Controller;

use App\Entity\FactoryUnit;
use App\Entity\User;
use App\Form\FactoryUnitType;
use App\Security\Voter\ModuleAccessVoter;
use App\Service\FactoryUnitService;
use App\Service\JsonResponder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/usine')]
#[IsGranted('ROLE_USER')]
final class FactoryUnitController extends AbstractController
{
    public function __construct(
        private readonly FactoryUnitService $factoryUnitService,
        private readonly JsonResponder $jsonResponder,
    ) {
    }

    #[Route('', name: 'app_factory_unit_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $this->denyAccessUnlessGranted(ModuleAccessVoter::ACCESS, 'factory');
        $filters = $this->filtersFromRequest($request);
        $items = $this->factoryUnitService->search($this->currentUser(), $filters);

        return $this->render('factory_unit/index.html.twig', [
            'items' => $items,
            'filters' => $filters,
            'stats' => $this->stats($items),
            'type_choices' => FactoryUnit::TYPE_LABELS,
            'status_choices' => FactoryUnit::STATUS_LABELS,
            'create_form' => $this->buildForm(new FactoryUnit(), 'app_factory_unit_create'),
        ]);
    }

    #[Route('/creer', name: 'app_factory_unit_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(ModuleAccessVoter::ACCESS, 'factory');
        $unit = new FactoryUnit();
        $form = $this->buildForm($unit, 'app_factory_unit_create');
        $form->handleRequest($request);
        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->jsonResponder->invalidForm($form);
        }

        try {
            $this->factoryUnitService->save($unit, $this->currentUser());
        } catch (\DomainException $exception) {
            return $this->jsonResponder->error($exception->getMessage(), [], 422);
        }

        return $this->jsonResponder->success('Piece usine creee et charge cout de revient preparee.', ['reload' => true], 201);
    }

    #[Route('/{id}/formulaire', name: 'app_factory_unit_form', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function form(FactoryUnit $unit): Response
    {
        $this->denyAccessUnlessGranted(ModuleAccessVoter::ACCESS, 'factory');

        return $this->render('factory_unit/_form_modal.html.twig', [
            'form' => $this->buildForm($unit, 'app_factory_unit_update', ['id' => $unit->getId()]),
            'title' => 'Modifier une piece usine',
            'submit_label' => 'Enregistrer',
        ]);
    }

    #[Route('/{id}/modifier', name: 'app_factory_unit_update', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function update(FactoryUnit $unit, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(ModuleAccessVoter::ACCESS, 'factory');
        $form = $this->buildForm($unit, 'app_factory_unit_update', ['id' => $unit->getId()]);
        $form->handleRequest($request);
        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->jsonResponder->invalidForm($form);
        }

        try {
            $this->factoryUnitService->save($unit, $this->currentUser());
        } catch (\DomainException $exception) {
            return $this->jsonResponder->error($exception->getMessage(), [], 422);
        }

        return $this->jsonResponder->success('Piece usine mise a jour.', ['reload' => true]);
    }

    #[Route('/{id}/saturation', name: 'app_factory_unit_saturation', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function saturation(FactoryUnit $unit, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(ModuleAccessVoter::ACCESS, 'factory');
        $this->assertCsrf($request, 'factory_unit_saturation_'.$unit->getId());
        $saturated = $this->factoryUnitService->toggleSaturation($unit, $this->currentUser());

        return $this->jsonResponder->success(
            $saturated ? 'Piece marquee saturee.' : 'Piece disponible pour stockage.',
            ['reload' => true],
        );
    }

    #[Route('/{id}/disponibilite', name: 'app_factory_unit_active', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function active(FactoryUnit $unit, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(ModuleAccessVoter::ACCESS, 'factory');
        $this->assertCsrf($request, 'factory_unit_active_'.$unit->getId());
        $active = $this->factoryUnitService->toggleActive($unit, $this->currentUser());

        return $this->jsonResponder->success(
            $active ? 'Piece disponible dans les selections.' : 'Piece retiree des selections.',
            ['reload' => true],
        );
    }

    #[Route('/{id}/supprimer', name: 'app_factory_unit_delete', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    public function delete(FactoryUnit $unit, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(ModuleAccessVoter::ACCESS, 'factory');
        $this->assertCsrf($request, 'factory_unit_delete_'.$unit->getId());

        try {
            $this->factoryUnitService->delete($unit, $this->currentUser());
        } catch (\DomainException $exception) {
            return $this->jsonResponder->error($exception->getMessage(), [], 422);
        }

        return $this->jsonResponder->success('Piece usine vide supprimee.', ['reload' => true]);
    }

    /** @param array<string, int|string|null> $parameters */
    private function buildForm(FactoryUnit $unit, string $route, array $parameters = []): FormInterface
    {
        return $this->createForm(FactoryUnitType::class, $unit, [
            'action' => $this->generateUrl($route, $parameters),
        ]);
    }

    /** @return array<string, string> */
    private function filtersFromRequest(Request $request): array
    {
        return [
            'q' => trim((string) $request->query->get('q', '')),
            'type' => trim((string) $request->query->get('type', '')),
            'status' => trim((string) $request->query->get('status', '')),
            'saturation' => trim((string) $request->query->get('saturation', '')),
        ];
    }

    /** @param list<FactoryUnit> $items @return array<string, int> */
    private function stats(array $items): array
    {
        $stats = [
            'total' => count($items),
            'active' => 0,
            'saturated' => 0,
            'tunnels' => 0,
            'coldRooms' => 0,
        ];

        foreach ($items as $item) {
            if ($item->isActive()) {
                ++$stats['active'];
            }
            if ($item->isSaturated()) {
                ++$stats['saturated'];
            }
            if ($item->getType() === FactoryUnit::TYPE_TUNNEL) {
                ++$stats['tunnels'];
            }
            if (in_array($item->getType(), [FactoryUnit::TYPE_NEGATIVE_ROOM, FactoryUnit::TYPE_POSITIVE_ROOM], true)) {
                ++$stats['coldRooms'];
            }
        }

        return $stats;
    }

    private function assertCsrf(Request $request, string $id): void
    {
        $payload = $request->toArray();
        if (!$this->isCsrfTokenValid($id, (string) ($payload['token'] ?? ''))) {
            throw new \DomainException('Jeton de securite invalide. Rechargez la page.');
        }
    }

    private function currentUser(): User
    {
        $user = $this->getUser();
        \assert($user instanceof User);

        return $user;
    }
}
