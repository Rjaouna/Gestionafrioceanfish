<?php

namespace App\Controller;

use App\Entity\InterimWorker;
use App\Entity\InterimWorkerDocument;
use App\Entity\User;
use App\Form\InterimWorkerDoNotRecallType;
use App\Form\InterimWorkerEndMissionType;
use App\Form\InterimWorkerStatusActionType;
use App\Form\InterimWorkerType;
use App\Security\Voter\InterimWorkerVoter;
use App\Security\Voter\ModuleAccessVoter;
use App\Service\InterimWorkerService;
use App\Service\InterimWorkerStorageService;
use App\Service\JsonResponder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/fiches-interimaires')]
#[IsGranted('ROLE_USER')]
final class InterimWorkerController extends AbstractController
{
    public function __construct(
        private readonly InterimWorkerService $workerService,
        private readonly InterimWorkerStorageService $storage,
        private readonly JsonResponder $jsonResponder,
    ) {
    }

    #[Route('', name: 'app_interim_worker_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $this->denyAccessUnlessGranted(ModuleAccessVoter::ACCESS, 'interimaires');
        $result = $this->workerService->search($this->currentUser(), $this->filtersFromRequest($request), max(1, $request->query->getInt('page', 1)));

        return $this->render('interim_worker/index.html.twig', [
            'workers' => $result['items'],
            'pagination' => $result,
            'filters' => $result['filters'],
            'filter_choices' => $this->workerService->filterChoices($this->currentUser()),
            'create_form' => $this->buildForm(new InterimWorker(), 'app_interim_worker_create'),
        ]);
    }

    #[Route('/recherche', name: 'app_interim_worker_search', methods: ['GET'])]
    public function search(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(ModuleAccessVoter::ACCESS, 'interimaires');
        $result = $this->workerService->search($this->currentUser(), $this->filtersFromRequest($request), max(1, $request->query->getInt('page', 1)));

        return $this->jsonResponder->success('Recherche mise a jour.', [
            'html' => $this->renderView('interim_worker/_grid.html.twig', [
                'workers' => $result['items'],
                'pagination' => $result,
            ]),
            'count' => $result['total'],
            'page' => $result['page'],
            'pages' => $result['pages'],
        ]);
    }

    #[Route('/nouveau', name: 'app_interim_worker_new', methods: ['GET'])]
    public function new(Request $request): Response
    {
        $this->denyAccessUnlessGranted(InterimWorkerVoter::CREATE);
        $worker = new InterimWorker();

        return $this->render('interim_worker/new.html.twig', [
            'form' => $this->buildForm($worker, 'app_interim_worker_create'),
            'worker' => $worker,
            'filter_choices' => $this->workerService->filterChoices($this->currentUser()),
        ]);
    }

    #[Route('/nouveau', name: 'app_interim_worker_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(InterimWorkerVoter::CREATE);
        $worker = new InterimWorker();
        $form = $this->buildForm($worker, 'app_interim_worker_create');
        $form->handleRequest($request);
        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->jsonResponder->invalidForm($form);
        }

        try {
            $this->workerService->create($worker, $form->get('photo')->getData(), $this->uploadedDocuments($form), $this->currentUser());
        } catch (\DomainException $exception) {
            return $this->jsonResponder->error($exception->getMessage(), [], 422);
        }

        return $this->jsonResponder->success('L’intérimaire a ete cree.', ['reload' => true], 201);
    }

    #[Route('/{id}', name: 'app_interim_worker_view', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function view(InterimWorker $worker, Request $request): Response
    {
        $this->denyAccessUnlessGranted(InterimWorkerVoter::VIEW, $worker);

        if (!$request->isXmlHttpRequest()) {
            return $this->render('interim_worker/show.html.twig', [
                'worker' => $worker,
            ]);
        }

        return $this->render('interim_worker/_details_modal.html.twig', [
            'worker' => $worker,
        ]);
    }

    #[Route('/{id}/imprimer', name: 'app_interim_worker_print', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function print(InterimWorker $worker): Response
    {
        $this->denyAccessUnlessGranted(InterimWorkerVoter::PRINT, $worker);

        return $this->render('interim_worker/print.html.twig', [
            'worker' => $worker,
        ]);
    }

    #[Route('/{id}/photo', name: 'app_interim_worker_photo', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function photo(InterimWorker $worker): BinaryFileResponse
    {
        $this->denyAccessUnlessGranted(InterimWorkerVoter::VIEW, $worker);
        $response = new BinaryFileResponse($this->storage->photoFile($worker));
        $response->headers->set('Content-Type', $worker->getPhotoMimeType() ?: 'image/jpeg');
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_INLINE, $worker->getPhotoOriginalFileName() ?: 'photo');
        $response->headers->set('Cache-Control', 'private, no-store');

        return $response;
    }

    #[Route('/{id}/modifier', name: 'app_interim_worker_form', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function form(InterimWorker $worker): Response
    {
        $this->denyAccessUnlessGranted(InterimWorkerVoter::EDIT, $worker);

        return $this->render('interim_worker/_form_modal.html.twig', [
            'form' => $this->buildForm($worker, 'app_interim_worker_edit', ['id' => $worker->getId()]),
            'worker' => $worker,
            'title' => sprintf('Modifier %s', $worker->getFullName()),
            'submit_label' => 'Enregistrer',
            'filter_choices' => $this->workerService->filterChoices($this->currentUser()),
        ]);
    }

    #[Route('/{id}/modifier', name: 'app_interim_worker_edit', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function edit(InterimWorker $worker, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(InterimWorkerVoter::EDIT, $worker);
        $form = $this->buildForm($worker, 'app_interim_worker_edit', ['id' => $worker->getId()]);
        $form->handleRequest($request);
        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->jsonResponder->invalidForm($form);
        }

        try {
            $this->workerService->update($worker, $form->get('photo')->getData(), $this->uploadedDocuments($form), $this->currentUser());
        } catch (\DomainException $exception) {
            return $this->jsonResponder->error($exception->getMessage(), [], 422);
        }

        return $this->jsonResponder->success('L’intérimaire a ete modifie.', ['reload' => true]);
    }

    #[Route('/{id}/fin-mission/formulaire', name: 'app_interim_worker_end_mission_form', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function endMissionForm(InterimWorker $worker): Response
    {
        $this->denyAccessUnlessGranted(InterimWorkerVoter::EDIT, $worker);

        return $this->render('interim_worker/_end_mission_modal.html.twig', [
            'worker' => $worker,
            'form' => $this->createForm(InterimWorkerEndMissionType::class, null, [
                'action' => $this->generateUrl('app_interim_worker_end_mission', ['id' => $worker->getId()]),
                'mission_end_date' => $worker->getMissionEndDate(),
            ]),
        ]);
    }

    #[Route('/{id}/fin-mission', name: 'app_interim_worker_end_mission', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function endMission(InterimWorker $worker, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(InterimWorkerVoter::EDIT, $worker);
        $form = $this->createForm(InterimWorkerEndMissionType::class, null, [
            'action' => $this->generateUrl('app_interim_worker_end_mission', ['id' => $worker->getId()]),
            'mission_end_date' => $worker->getMissionEndDate(),
        ]);
        $form->handleRequest($request);
        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->jsonResponder->invalidForm($form);
        }

        try {
            $this->workerService->endMission(
                $worker,
                $this->dateFromForm($form, 'missionEndDate'),
                (string) $form->get('reason')->getData(),
                $this->currentUser(),
            );
        } catch (\DomainException $exception) {
            return $this->jsonResponder->error($exception->getMessage(), [], 422);
        }

        return $this->jsonResponder->success('Fin de mission enregistree.', ['reload' => true]);
    }

    #[Route('/{id}/ne-plus-rappeler/formulaire', name: 'app_interim_worker_do_not_recall_form', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function doNotRecallForm(InterimWorker $worker): Response
    {
        $this->denyAccessUnlessGranted(InterimWorkerVoter::EDIT, $worker);

        return $this->render('interim_worker/_do_not_recall_modal.html.twig', [
            'worker' => $worker,
            'form' => $this->createForm(InterimWorkerDoNotRecallType::class, null, [
                'action' => $this->generateUrl('app_interim_worker_do_not_recall', ['id' => $worker->getId()]),
                'action_date' => $worker->getDoNotRecallAt(),
            ]),
        ]);
    }

    #[Route('/{id}/ne-plus-rappeler', name: 'app_interim_worker_do_not_recall', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function doNotRecall(InterimWorker $worker, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(InterimWorkerVoter::EDIT, $worker);
        $form = $this->createForm(InterimWorkerDoNotRecallType::class, null, [
            'action' => $this->generateUrl('app_interim_worker_do_not_recall', ['id' => $worker->getId()]),
            'action_date' => $worker->getDoNotRecallAt(),
        ]);
        $form->handleRequest($request);
        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->jsonResponder->invalidForm($form);
        }

        try {
            $this->workerService->markDoNotRecall(
                $worker,
                $this->dateFromForm($form, 'actionDate'),
                (string) $form->get('reason')->getData(),
                $this->currentUser(),
            );
        } catch (\DomainException $exception) {
            return $this->jsonResponder->error($exception->getMessage(), [], 422);
        }

        return $this->jsonResponder->success('La personne est marquee comme a ne pas rappeler.', ['reload' => true]);
    }

    #[Route('/{id}/statut/formulaire', name: 'app_interim_worker_status_form', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function statusForm(InterimWorker $worker): Response
    {
        $this->denyAccessUnlessGranted(InterimWorkerVoter::EDIT, $worker);

        return $this->render('interim_worker/_status_modal.html.twig', [
            'worker' => $worker,
            'form' => $this->createForm(InterimWorkerStatusActionType::class, null, [
                'action' => $this->generateUrl('app_interim_worker_status', ['id' => $worker->getId()]),
                'current_status' => $worker->getStatus(),
            ]),
        ]);
    }

    #[Route('/{id}/statut', name: 'app_interim_worker_status', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function status(InterimWorker $worker, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(InterimWorkerVoter::EDIT, $worker);
        $form = $this->createForm(InterimWorkerStatusActionType::class, null, [
            'action' => $this->generateUrl('app_interim_worker_status', ['id' => $worker->getId()]),
            'current_status' => $worker->getStatus(),
        ]);
        $form->handleRequest($request);
        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->jsonResponder->invalidForm($form);
        }

        try {
            $this->workerService->changeStatus(
                $worker,
                (string) $form->get('status')->getData(),
                $this->dateFromForm($form, 'actionDate'),
                (string) $form->get('reason')->getData(),
                $this->currentUser(),
            );
        } catch (\DomainException $exception) {
            return $this->jsonResponder->error($exception->getMessage(), [], 422);
        }

        return $this->jsonResponder->success('Statut mis a jour.', ['reload' => true]);
    }

    #[Route('/documents/{id}/telecharger', name: 'app_interim_worker_document_download', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function downloadDocument(InterimWorkerDocument $document): BinaryFileResponse
    {
        $this->denyAccessUnlessGranted(InterimWorkerVoter::DOWNLOAD_DOCUMENT, $document);
        $response = new BinaryFileResponse($this->storage->documentFile($document));
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $document->getOriginalFileName() ?: 'document');
        $response->headers->set('Cache-Control', 'private, no-store');

        return $response;
    }

    #[Route('/documents/{id}', name: 'app_interim_worker_document_delete', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    public function deleteDocument(InterimWorkerDocument $document, Request $request): JsonResponse
    {
        $worker = $document->getWorker();
        $this->denyAccessUnlessGranted(InterimWorkerVoter::EDIT, $worker);
        $payload = $request->toArray();
        $this->assertCsrf((string) ($payload['token'] ?? ''), 'delete_interim_worker_document_'.$document->getId());
        $this->workerService->deleteDocument($document, $this->currentUser());

        return $this->jsonResponder->success('Le document a ete supprime.', ['reload' => true]);
    }

    #[Route('/{id}/supprimer', name: 'app_interim_worker_delete', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    public function delete(InterimWorker $worker, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(InterimWorkerVoter::DELETE, $worker);
        $payload = $request->toArray();
        $this->assertCsrf((string) ($payload['token'] ?? ''), 'delete_interim_worker_'.$worker->getId());
        $movedToTrash = $this->workerService->delete($worker, $this->currentUser());

        return $this->jsonResponder->success(
            $movedToTrash ? 'L’intérimaire a ete deplace dans la corbeille.' : 'L’intérimaire a ete supprime.',
            ['reload' => true],
        );
    }

    /** @param array<string, int|string|null> $routeParameters */
    private function buildForm(InterimWorker $worker, string $route, array $routeParameters = []): FormInterface
    {
        $isPersisted = $worker->getId() !== null;

        return $this->createForm(InterimWorkerType::class, $worker, [
            'action' => $this->generateUrl($route, $routeParameters),
            'max_photo_size' => $this->storage->photoMaxSize(),
            'max_document_size' => $this->storage->documentMaxSize(),
            'photo_mime_types' => $this->storage->photoMimeTypes(),
            'document_mime_types' => $this->storage->documentMimeTypes(),
            'show_registration_number' => $isPersisted,
            'show_hire_date' => false,
            'show_mission_end_date' => false,
            'show_status' => $isPersisted,
            'position_choices' => $this->workerService->positionChoices(),
        ]);
    }

    /** @return array<string, string> */
    private function filtersFromRequest(Request $request): array
    {
        return [
            'q' => trim((string) $request->query->get('q', '')),
            'position' => trim((string) $request->query->get('position', '')),
            'workerType' => trim((string) $request->query->get('workerType', '')),
            'familySituation' => trim((string) $request->query->get('familySituation', '')),
            'status' => trim((string) $request->query->get('status', '')),
            'hireDate' => trim((string) $request->query->get('hireDate', '')),
        ];
    }

    /** @return list<\Symfony\Component\HttpFoundation\File\UploadedFile> */
    private function uploadedDocuments(FormInterface $form): array
    {
        $documents = $form->get('documents')->getData();
        if (!is_iterable($documents)) {
            return [];
        }

        if (is_array($documents)) {
            return array_values(array_filter($documents));
        }

        return array_values(array_filter(iterator_to_array($documents)));
    }

    private function assertCsrf(string $token, string $id): void
    {
        if (!$this->isCsrfTokenValid($id, $token)) {
            throw new \DomainException('Jeton de sécurité invalide. Rechargez la page.');
        }
    }

    private function dateFromForm(FormInterface $form, string $field): \DateTimeImmutable
    {
        $value = $form->get($field)->getData();
        if ($value instanceof \DateTimeImmutable) {
            return $value;
        }

        if ($value instanceof \DateTimeInterface) {
            return \DateTimeImmutable::createFromInterface($value);
        }

        throw new \DomainException('Date invalide.');
    }

    private function currentUser(): User
    {
        $user = $this->getUser();
        \assert($user instanceof User);

        return $user;
    }
}
