<?php

namespace App\Controller;

use App\Entity\Document;
use App\Entity\User;
use App\Form\DocumentEmailType;
use App\Security\Voter\DocumentVoter;
use App\Service\DocumentMailerService;
use App\Service\JsonResponder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/documents/{id}/email', requirements: ['id' => '\d+'])]
#[IsGranted('ROLE_USER')]
final class DocumentEmailController extends AbstractController
{
    public function __construct(
        private readonly DocumentMailerService $mailer,
        private readonly JsonResponder $jsonResponder,
    ) {
    }

    #[Route('', name: 'app_document_email_modal', methods: ['GET'])]
    public function modal(Document $document): Response
    {
        $this->denyAccessUnlessGranted(DocumentVoter::EMAIL, $document);

        return $this->render('document/_email_modal.html.twig', [
            'document' => $document,
            'form' => $this->buildForm($document),
        ]);
    }

    #[Route('', name: 'app_document_email_send', methods: ['POST'])]
    public function send(Document $document, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(DocumentVoter::EMAIL, $document);
        $form = $this->buildForm($document);
        $form->handleRequest($request);
        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->jsonResponder->invalidForm($form);
        }

        $data = $form->getData();
        \assert(is_array($data));

        try {
            $this->mailer->sendDocumentByEmail(
                $document,
                $this->currentUser(),
                (string) $data['recipientEmail'],
            );
        } catch (TransportExceptionInterface) {
            return $this->jsonResponder->error('Le document n’a pas pu être envoyé : le service e-mail a refusé l’envoi.', [], 500);
        } catch (\Throwable $exception) {
            return $this->jsonResponder->error($exception->getMessage(), [], 422);
        }

        return $this->jsonResponder->success('Le document a été envoyé par e-mail.', ['closeModal' => true]);
    }

    private function buildForm(Document $document): FormInterface
    {
        return $this->createForm(DocumentEmailType::class, null, [
            'action' => $this->generateUrl('app_document_email_send', ['id' => $document->getId()]),
            'csrf_token_id' => 'document_email_'.$document->getId(),
        ]);
    }

    private function currentUser(): User
    {
        $user = $this->getUser();
        \assert($user instanceof User);

        return $user;
    }
}
