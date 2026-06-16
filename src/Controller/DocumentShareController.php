<?php

namespace App\Controller;

use App\Entity\Document;
use App\Entity\DocumentShare;
use App\Entity\User;
use App\Repository\DocumentShareRepository;
use App\Security\Voter\DocumentVoter;
use App\Service\DocumentShareService;
use App\Service\JsonResponder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/documents/{id}/partages', requirements: ['id' => '\d+'])]
#[IsGranted('ROLE_USER')]
final class DocumentShareController extends AbstractController
{
    public function __construct(
        private readonly DocumentShareService $shareService,
        private readonly DocumentShareRepository $shareRepository,
        private readonly JsonResponder $jsonResponder,
    ) {
    }

    #[Route('', name: 'app_document_share_modal', methods: ['GET'])]
    public function modal(Document $document): Response
    {
        $this->denyAccessUnlessGranted(DocumentVoter::SHARE, $document);

        return $this->renderShareModal($document);
    }

    #[Route('/recherche-utilisateurs', name: 'app_document_share_user_search', methods: ['GET'])]
    public function searchUsers(Document $document, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(DocumentVoter::SHARE, $document);

        return $this->jsonResponder->success('Utilisateurs trouvés.', [
            'users' => $this->shareService->searchRecipients($document, (string) $request->query->get('q', ''), $this->currentUser()),
        ]);
    }

    #[Route('', name: 'app_document_share_save', methods: ['POST'])]
    public function share(Document $document, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(DocumentVoter::SHARE, $document);
        $payload = $request->toArray();
        $this->assertCsrf((string) ($payload['token'] ?? ''), 'share_document_'.$document->getId());
        $expiresAt = null;
        if (!empty($payload['expiresAt'])) {
            $expiresAt = new \DateTimeImmutable((string) $payload['expiresAt']);
        }

        try {
            $this->shareService->share(
                $document,
                (int) ($payload['userId'] ?? 0),
                filter_var($payload['canDownload'] ?? true, FILTER_VALIDATE_BOOL),
                $expiresAt,
                $this->currentUser(),
            );
        } catch (TransportExceptionInterface) {
            return $this->jsonResponder->error('Le partage n’a pas été créé : l’e-mail n’a pas pu être envoyé.', [], 500);
        } catch (\DomainException $exception) {
            return $this->jsonResponder->error($exception->getMessage(), [], 422);
        }

        return $this->jsonResponder->success('Le document a été partagé et l’e-mail a été envoyé.', [
            'html' => $this->renderShareModal($document)->getContent(),
        ]);
    }

    #[Route('/{shareId}', name: 'app_document_share_remove', requirements: ['shareId' => '\d+'], methods: ['DELETE'])]
    public function remove(Document $document, int $shareId, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(DocumentVoter::SHARE, $document);
        $payload = $request->toArray();
        $this->assertCsrf((string) ($payload['token'] ?? ''), 'remove_document_share_'.$document->getId());
        $share = $this->shareRepository->find($shareId);
        if (!$share instanceof DocumentShare || $share->getDocument()?->getId() !== $document->getId()) {
            return $this->jsonResponder->error('Ce partage est introuvable.', [], 404);
        }

        $this->shareService->remove($share, $this->currentUser());

        return $this->jsonResponder->success('Le partage a été retiré.', [
            'html' => $this->renderShareModal($document)->getContent(),
        ]);
    }

    private function renderShareModal(Document $document): Response
    {
        return $this->render('document/_share_modal.html.twig', [
            'document' => $document,
            'shares' => $this->shareService->getActiveShares($document, $this->currentUser()),
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
