<?php

namespace App\Controller\Expense;

use App\Entity\Expense;
use App\Entity\ExpenseDocument;
use App\Entity\User;
use App\Security\Voter\ExpenseVoter;
use App\Service\Expense\ExpenseDocumentService;
use App\Service\JsonResponder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/depenses')]
#[IsGranted('ROLE_USER')]
final class ExpenseDocumentController extends AbstractController
{
    public function __construct(
        private readonly ExpenseDocumentService $documentService,
        private readonly JsonResponder $jsonResponder,
    ) {
    }

    #[Route('/{id}/justificatif', name: 'app_expense_document_upload', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function upload(Expense $expense, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(ExpenseVoter::EDIT, $expense);
        if (!$this->isCsrfTokenValid('upload_expense_document_'.$expense->getId(), (string) $request->request->get('token', ''))) {
            throw new \DomainException('Jeton de sécurité invalide. Rechargez la page.');
        }

        $file = $request->files->get('file');
        if (!$file instanceof UploadedFile) {
            return $this->jsonResponder->error('Le fichier est obligatoire.', [], 422);
        }

        $this->documentService->replacePrimary($expense, $file, (string) $request->request->get('documentType', ExpenseDocument::TYPE_INVOICE), $this->currentUser());

        return $this->jsonResponder->success('Le justificatif a été ajouté.', ['reload' => true]);
    }

    #[Route('/justificatifs/{id}/telecharger', name: 'app_expense_document_download', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function download(ExpenseDocument $document): BinaryFileResponse
    {
        $this->denyAccessUnlessGranted(ExpenseVoter::DOWNLOAD_DOCUMENT, $document);
        $response = new BinaryFileResponse($this->documentService->file($document, $this->currentUser()));
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $this->documentService->downloadFileName($document));
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }

    #[Route('/justificatifs/{id}', name: 'app_expense_document_delete', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    public function delete(ExpenseDocument $document, Request $request): JsonResponse
    {
        $payload = $request->toArray();
        if (!$this->isCsrfTokenValid('delete_expense_document_'.$document->getId(), (string) ($payload['token'] ?? ''))) {
            throw new \DomainException('Jeton de sécurité invalide. Rechargez la page.');
        }

        $this->documentService->delete($document, $this->currentUser());

        return $this->jsonResponder->success('Le justificatif a été supprimé.', ['reload' => true]);
    }

    private function currentUser(): User
    {
        $user = $this->getUser();
        \assert($user instanceof User);

        return $user;
    }
}
