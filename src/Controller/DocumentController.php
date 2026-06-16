<?php

namespace App\Controller;

use App\Entity\Document;
use App\Entity\User;
use App\Form\DocumentType;
use App\Security\Voter\DocumentVoter;
use App\Security\Voter\ModuleAccessVoter;
use App\Service\DocumentService;
use App\Service\DocumentStorageService;
use App\Service\JsonResponder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/documents')]
#[IsGranted('ROLE_USER')]
final class DocumentController extends AbstractController
{
    public function __construct(
        private readonly DocumentService $documentService,
        private readonly DocumentStorageService $storage,
        private readonly JsonResponder $jsonResponder,
    ) {
    }

    #[Route('', name: 'app_document_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $this->denyAccessUnlessGranted(ModuleAccessVoter::ACCESS, 'documents');
        $this->denyAccessUnlessGranted(DocumentVoter::CREATE);
        $result = $this->documentService->search($this->currentUser(), (string) $request->query->get('q', ''), max(1, $request->query->getInt('page', 1)));

        return $this->render('document/index.html.twig', [
            'documents' => $result['items'],
            'pagination' => $result,
            'create_form' => $this->buildForm(new Document(), true, 'app_document_create'),
        ]);
    }

    #[Route('/recherche', name: 'app_document_search', methods: ['GET'])]
    public function search(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(ModuleAccessVoter::ACCESS, 'documents');
        $result = $this->documentService->search($this->currentUser(), (string) $request->query->get('q', ''), max(1, $request->query->getInt('page', 1)));

        return $this->jsonResponder->success('Recherche mise à jour.', [
            'html' => $this->renderView('document/_document_grid.html.twig', [
                'documents' => $result['items'],
                'pagination' => $result,
            ]),
            'count' => $result['total'],
            'page' => $result['page'],
            'pages' => $result['pages'],
        ]);
    }

    #[Route('/creer', name: 'app_document_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(DocumentVoter::CREATE);
        $document = new Document();
        $form = $this->buildForm($document, true, 'app_document_create');
        $form->handleRequest($request);
        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->jsonResponder->invalidForm($form);
        }

        $file = $form->get('file')->getData();
        try {
            $this->documentService->create($document, $file, $this->currentUser());
        } catch (\DomainException $exception) {
            return $this->jsonResponder->error($exception->getMessage(), [], 422);
        }

        return $this->jsonResponder->success('Le document a été ajouté.', ['reload' => true], 201);
    }

    #[Route('/{id}/consulter', name: 'app_document_view', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function view(Document $document, Request $request): Response
    {
        $this->denyAccessUnlessGranted(DocumentVoter::VIEW, $document);

        if (!$request->isXmlHttpRequest()) {
            return $this->render('document/show.html.twig', [
                'document' => $document,
            ]);
        }

        return $this->render('document/_details_modal.html.twig', [
            'document' => $document,
        ]);
    }

    #[Route('/{id}/formulaire', name: 'app_document_form', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function form(Document $document): Response
    {
        $this->denyAccessUnlessGranted(DocumentVoter::EDIT, $document);

        return $this->render('document/_form_modal.html.twig', [
            'form' => $this->buildForm($document, false, 'app_document_edit', ['id' => $document->getId()]),
            'title' => 'Modifier un document',
            'submit_label' => 'Enregistrer',
        ]);
    }

    #[Route('/{id}/modifier', name: 'app_document_edit', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function edit(Document $document, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(DocumentVoter::EDIT, $document);
        $form = $this->buildForm($document, false, 'app_document_edit', ['id' => $document->getId()]);
        $form->handleRequest($request);
        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->jsonResponder->invalidForm($form);
        }

        try {
            $this->documentService->update($document, $form->get('file')->getData(), $this->currentUser());
        } catch (\DomainException $exception) {
            return $this->jsonResponder->error($exception->getMessage(), [], 422);
        }

        return $this->jsonResponder->success('Le document a été modifié.', ['reload' => true]);
    }

    #[Route('/{id}/telecharger', name: 'app_document_download', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function download(Document $document): BinaryFileResponse
    {
        $this->denyAccessUnlessGranted(DocumentVoter::DOWNLOAD, $document);
        $response = new BinaryFileResponse($this->storage->file($document));
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $this->storage->downloadFileName($document));
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }

    #[Route('/{id}/archive', name: 'app_document_archive', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function archive(Document $document, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(DocumentVoter::ARCHIVE, $document);
        $payload = $request->toArray();
        $this->assertCsrf((string) ($payload['token'] ?? ''), 'archive_document_'.$document->getId());
        $active = $this->documentService->toggleArchive($document, $this->currentUser());

        return $this->jsonResponder->success(
            $active ? 'Le document a été désarchivé.' : 'Le document a été archivé.',
            ['reload' => true],
        );
    }

    #[Route('/{id}', name: 'app_document_delete', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    public function delete(Document $document, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(DocumentVoter::DELETE, $document);
        $payload = $request->toArray();
        $this->assertCsrf((string) ($payload['token'] ?? ''), 'delete_document_'.$document->getId());
        $this->documentService->delete($document, $this->currentUser());

        return $this->jsonResponder->success('Le document a été supprimé.', ['reload' => true]);
    }

    /** @param array<string, int|string> $parameters */
    private function buildForm(Document $document, bool $fileRequired, string $route, array $parameters = []): \Symfony\Component\Form\FormInterface
    {
        return $this->createForm(DocumentType::class, $document, [
            'action' => $this->generateUrl($route, $parameters),
            'file_required' => $fileRequired,
            'max_file_size' => $this->storage->maxFileSize(),
            'allowed_mime_types' => $this->storage->allowedMimeTypes(),
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
