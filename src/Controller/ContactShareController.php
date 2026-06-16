<?php

namespace App\Controller;

use App\Entity\Contact;
use App\Entity\User;
use App\Security\Voter\ContactVoter;
use App\Service\ContactShareService;
use App\Service\JsonResponder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/contacts/{id}/partages', requirements: ['id' => '\d+'])]
#[IsGranted('ROLE_USER')]
final class ContactShareController extends AbstractController
{
    public function __construct(
        private readonly ContactShareService $shareService,
        private readonly JsonResponder $jsonResponder,
    ) {
    }

    #[Route('', name: 'app_contact_share_modal', methods: ['GET'])]
    public function modal(Contact $contact): Response
    {
        $this->denyAccessUnlessGranted(ContactVoter::SHARE, $contact);

        return $this->render('contact/_share_modal.html.twig', [
            'contact' => $contact,
            'share_matrix' => $this->shareService->getShareMatrix($contact, $this->currentUser()),
        ]);
    }

    #[Route('', name: 'app_contact_share_save', methods: ['POST'])]
    public function save(Contact $contact, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(ContactVoter::SHARE, $contact);
        $payload = $request->toArray();
        if (!$this->isCsrfTokenValid('share_contact_'.$contact->getId(), (string) ($payload['token'] ?? ''))) {
            throw new \DomainException('Jeton de sécurité invalide. Rechargez la page.');
        }

        $items = is_array($payload['shares'] ?? null) ? $payload['shares'] : [];
        $this->shareService->synchronize($contact, $items, $this->currentUser());

        return $this->jsonResponder->success('Les partages du contact ont été enregistrés.');
    }

    private function currentUser(): User
    {
        $user = $this->getUser();
        \assert($user instanceof User);

        return $user;
    }
}
