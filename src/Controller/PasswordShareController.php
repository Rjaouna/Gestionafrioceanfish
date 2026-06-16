<?php

namespace App\Controller;

use App\Entity\PasswordEntry;
use App\Entity\User;
use App\Security\Voter\ModuleAccessVoter;
use App\Security\Voter\PasswordEntryVoter;
use App\Service\JsonResponder;
use App\Service\PasswordShareService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/mots-de-passe/{id}/partages', requirements: ['id' => '\d+'])]
#[IsGranted('ROLE_USER')]
final class PasswordShareController extends AbstractController
{
    public function __construct(
        private readonly PasswordShareService $shareService,
        private readonly JsonResponder $jsonResponder,
    ) {
    }

    #[Route('', name: 'app_password_share_modal', methods: ['GET'])]
    public function modal(PasswordEntry $entry): Response
    {
        $this->denyAccessUnlessGranted(ModuleAccessVoter::ACCESS, 'passwords');
        $this->denyAccessUnlessGranted(PasswordEntryVoter::SHARE, $entry);

        return $this->render('password/_share_modal.html.twig', [
            'entry' => $entry,
            'share_matrix' => $this->shareService->getShareMatrix($entry, $this->currentUser()),
        ]);
    }

    #[Route('', name: 'app_password_share_save', methods: ['POST'])]
    public function save(PasswordEntry $entry, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(ModuleAccessVoter::ACCESS, 'passwords');
        $this->denyAccessUnlessGranted(PasswordEntryVoter::SHARE, $entry);
        $payload = $request->toArray();
        if (!$this->isCsrfTokenValid('share_password_'.$entry->getId(), (string) ($payload['token'] ?? ''))) {
            throw new \DomainException('Jeton de sécurité invalide. Rechargez la page.');
        }

        $items = is_array($payload['shares'] ?? null) ? $payload['shares'] : [];
        $this->shareService->synchronize($entry, $items, $this->currentUser());

        return $this->jsonResponder->success('Les droits de partage ont été enregistrés.');
    }

    private function currentUser(): User
    {
        $user = $this->getUser();
        \assert($user instanceof User);

        return $user;
    }
}
