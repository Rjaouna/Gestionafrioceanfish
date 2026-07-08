<?php

namespace App\Controller;

use App\Entity\FishYieldStudy;
use App\Entity\User;
use App\Form\FishYieldStudyType;
use App\Security\Voter\FishYieldStudyVoter;
use App\Security\Voter\ModuleAccessVoter;
use App\Service\FishYieldStudy\FishYieldStudyPermissionService;
use App\Service\FishYieldStudy\FishYieldStudyService;
use App\Service\JsonResponder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/etudes-rendement-poisson')]
#[IsGranted('ROLE_USER')]
final class FishYieldStudyController extends AbstractController
{
    public function __construct(
        private readonly FishYieldStudyService $studyService,
        private readonly JsonResponder $jsonResponder,
    ) {
    }

    #[Route('', name: 'app_fish_yield_study_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $this->denyAccessUnlessGranted(ModuleAccessVoter::ACCESS, FishYieldStudyPermissionService::MODULE_SLUG);
        $result = $this->studyService->search($this->currentUser(), $this->filtersFromRequest($request), $request->query->getInt('page', 1));

        return $this->render('fish_yield_study/index.html.twig', [
            'items' => $result['items'],
            'pagination' => $result,
            'filters' => $result['filters'],
            'filter_choices' => $this->studyService->filterChoices($this->currentUser()),
        ]);
    }

    #[Route('/ajax/list', name: 'app_fish_yield_study_search', methods: ['GET'])]
    public function search(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(ModuleAccessVoter::ACCESS, FishYieldStudyPermissionService::MODULE_SLUG);
        $result = $this->studyService->search($this->currentUser(), $this->filtersFromRequest($request), $request->query->getInt('page', 1));

        return $this->jsonResponder->success('Liste mise a jour.', [
            'html' => $this->renderView('fish_yield_study/_grid.html.twig', [
                'items' => $result['items'],
                'pagination' => $result,
            ]),
            'count' => $result['total'],
            'page' => $result['page'],
            'pages' => $result['pages'],
        ]);
    }

    #[Route('/nouveau', name: 'app_fish_yield_study_new', methods: ['GET'])]
    public function new(): Response
    {
        $this->denyAccessUnlessGranted(FishYieldStudyVoter::CREATE);
        $study = new FishYieldStudy();

        return $this->render('fish_yield_study/new.html.twig', [
            'form' => $this->buildForm($study, 'app_fish_yield_study_create'),
            'item' => $study,
            'title' => 'Nouvelle etude rendement poisson',
            'submit_label' => 'Enregistrer l etude',
        ]);
    }

    #[Route('/nouveau', name: 'app_fish_yield_study_create', methods: ['POST'])]
    public function create(Request $request): Response
    {
        $this->denyAccessUnlessGranted(FishYieldStudyVoter::CREATE);
        $study = new FishYieldStudy();
        $form = $this->buildForm($study, 'app_fish_yield_study_create');
        $form->handleRequest($request);
        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->render('fish_yield_study/new.html.twig', [
                'form' => $form,
                'item' => $study,
                'title' => 'Nouvelle etude rendement poisson',
                'submit_label' => 'Enregistrer l etude',
            ], new Response(status: 422));
        }

        $this->studyService->create($study, $this->currentUser());
        $this->addFlash('success', 'Etude rendement poisson enregistree.');

        return $this->redirectToRoute('app_fish_yield_study_view', ['id' => $study->getId()]);
    }

    #[Route('/formulaire-terrain/imprimer', name: 'app_fish_yield_study_print_blank', methods: ['GET'])]
    public function printBlank(): Response
    {
        $this->denyAccessUnlessGranted(FishYieldStudyVoter::PRINT);

        return $this->render('fish_yield_study/print_terrain.html.twig', [
            'item' => null,
            'fields' => $this->printFields(),
            'generated_at' => new \DateTimeImmutable(),
            'generated_by' => $this->currentUser()->getEmail(),
            'blank' => true,
        ]);
    }

    #[Route('/{id}/voir', name: 'app_fish_yield_study_view', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function view(FishYieldStudy $study): Response
    {
        $this->denyAccessUnlessGranted(FishYieldStudyVoter::VIEW, $study);

        return $this->render('fish_yield_study/show.html.twig', [
            'item' => $study,
        ]);
    }

    #[Route('/{id}/imprimer', name: 'app_fish_yield_study_print', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function print(FishYieldStudy $study): Response
    {
        $this->denyAccessUnlessGranted(FishYieldStudyVoter::PRINT, $study);

        return $this->render('fish_yield_study/print_terrain.html.twig', [
            'item' => $study,
            'fields' => $this->printFields($study),
            'generated_at' => new \DateTimeImmutable(),
            'generated_by' => $this->currentUser()->getEmail(),
            'blank' => false,
        ]);
    }

    #[Route('/{id}/modifier', name: 'app_fish_yield_study_edit', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function edit(FishYieldStudy $study): Response
    {
        $this->denyAccessUnlessGranted(FishYieldStudyVoter::EDIT, $study);

        return $this->render('fish_yield_study/edit.html.twig', [
            'form' => $this->buildForm($study, 'app_fish_yield_study_update', ['id' => $study->getId()]),
            'item' => $study,
            'title' => sprintf('Modifier %s', $study->getReference()),
            'submit_label' => 'Enregistrer',
        ]);
    }

    #[Route('/{id}/modifier', name: 'app_fish_yield_study_update', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function update(FishYieldStudy $study, Request $request): Response
    {
        $this->denyAccessUnlessGranted(FishYieldStudyVoter::EDIT, $study);
        $form = $this->buildForm($study, 'app_fish_yield_study_update', ['id' => $study->getId()]);
        $form->handleRequest($request);
        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->render('fish_yield_study/edit.html.twig', [
                'form' => $form,
                'item' => $study,
                'title' => sprintf('Modifier %s', $study->getReference()),
                'submit_label' => 'Enregistrer',
            ], new Response(status: 422));
        }

        $this->studyService->update($study, $this->currentUser());
        $this->addFlash('success', 'Etude rendement poisson mise a jour.');

        return $this->redirectToRoute('app_fish_yield_study_view', ['id' => $study->getId()]);
    }

    #[Route('/{id}/supprimer', name: 'app_fish_yield_study_delete', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    public function delete(FishYieldStudy $study, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(FishYieldStudyVoter::DELETE, $study);
        $payload = $request->toArray();
        if (!$this->isCsrfTokenValid('delete_fish_yield_study_'.$study->getId(), (string) ($payload['token'] ?? ''))) {
            return $this->jsonResponder->error('Jeton de securite invalide. Rechargez la page.', [], 422);
        }

        $this->studyService->delete($study, $this->currentUser());

        return $this->jsonResponder->success('Etude supprimee.', [
            'redirectUrl' => $this->generateUrl('app_fish_yield_study_index'),
        ]);
    }

    /** @param array<string, int|string|null> $routeParameters */
    private function buildForm(FishYieldStudy $study, string $route, array $routeParameters = []): FormInterface
    {
        return $this->createForm(FishYieldStudyType::class, $study, [
            'action' => $this->generateUrl($route, $routeParameters),
        ]);
    }

    /** @return array<string, string> */
    private function filtersFromRequest(Request $request): array
    {
        return [
            'q' => trim((string) $request->query->get('q', '')),
            'dateFrom' => trim((string) $request->query->get('dateFrom', '')),
            'dateTo' => trim((string) $request->query->get('dateTo', '')),
            'clientName' => trim((string) $request->query->get('clientName', '')),
            'speciesName' => trim((string) $request->query->get('speciesName', '')),
            'sort' => trim((string) $request->query->get('sort', 'date')),
            'direction' => trim((string) $request->query->get('direction', 'desc')),
        ];
    }

    /** @return list<array{label: string, value: string, required: bool}> */
    private function printFields(?FishYieldStudy $study = null): array
    {
        $value = static fn (mixed $content): string => $study instanceof FishYieldStudy ? trim((string) $content) : '';
        $kg = static fn (float $content): string => $study instanceof FishYieldStudy ? number_format($content, 3, ',', ' ').' kg' : '';
        $percent = static fn (float $content): string => $study instanceof FishYieldStudy ? number_format($content, 2, ',', ' ').' %' : '';

        return [
            ['label' => 'Client', 'value' => $value($study?->getClientName()), 'required' => false],
            ['label' => 'Date etude', 'value' => $study?->getStudyDate()?->format('d/m/Y') ?? '', 'required' => true],
            ['label' => 'Operateur', 'value' => $value($study?->getOperatorName()), 'required' => false],
            ['label' => 'Nom de l espece', 'value' => $value($study?->getSpeciesName()), 'required' => true],
            ['label' => 'Autre poisson melange avec l espece', 'value' => $value($study?->hasMixedFish() ? ($study->getMixedFishName() ?: 'Oui') : 'Non'), 'required' => false],
            ['label' => 'Poids caisse matiere premiere', 'value' => $kg($study?->rawBoxWeightValue() ?? 0), 'required' => true],
            ['label' => 'Poids caisse apres decongelation', 'value' => $kg($study?->thawedBoxWeightValue() ?? 0), 'required' => true],
            ['label' => 'Taux eau calcule', 'value' => $percent($study?->waterRate() ?? 0), 'required' => false],
            ['label' => 'Moule - pieces par kg', 'value' => $study instanceof FishYieldStudy ? number_format($study->piecesPerKgValue(), 2, ',', ' ') : '', 'required' => true],
            ['label' => 'Poids produit fini filet', 'value' => $kg($study?->finishedProductWeightValue() ?? 0), 'required' => true],
            ['label' => 'Poids dechets', 'value' => $kg($study?->wasteWeightValue() ?? 0), 'required' => true],
            ['label' => 'Poids pertes', 'value' => $kg($study?->lossWeightValue() ?? 0), 'required' => true],
            ['label' => 'Poids conteneur a estimer', 'value' => $kg($study?->containerWeightValue() ?? 0), 'required' => false],
            ['label' => 'Observations qualite / texture / odeur', 'value' => $value($study?->getObservations()), 'required' => false],
        ];
    }

    private function currentUser(): User
    {
        $user = $this->getUser();
        \assert($user instanceof User);

        return $user;
    }
}
