<?php

namespace App\Controller;

use App\Entity\PasswordEntry;
use App\Entity\User;
use App\Form\PasswordEntryType;
use App\Security\Voter\ModuleAccessVoter;
use App\Security\Voter\PasswordEntryVoter;
use App\Service\JsonResponder;
use App\Service\PasswordEntryService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/mots-de-passe')]
#[IsGranted('ROLE_USER')]
final class PasswordEntryController extends AbstractController
{
    public function __construct(
        private readonly PasswordEntryService $passwordService,
        private readonly JsonResponder $jsonResponder,
    ) {
    }

    #[Route('', name: 'app_password_index', methods: ['GET'])]
    public function index(): Response
    {
        $this->denyAccessUnlessGranted(ModuleAccessVoter::ACCESS, 'passwords');
        $user = $this->currentUser();
        $createForm = $this->createForm(PasswordEntryType::class, new PasswordEntry(), [
            'action' => $this->generateUrl('app_password_create'),
        ]);

        return $this->render('password/index.html.twig', [
            'entries' => $this->passwordService->getVisibleEntries($user),
            'create_form' => $createForm,
            'pending_validation_count' => $this->passwordService->countPendingValidation($user),
        ]);
    }

    #[Route('/creer', name: 'app_password_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(ModuleAccessVoter::ACCESS, 'passwords');
        $entry = new PasswordEntry();
        $form = $this->createForm(PasswordEntryType::class, $entry);
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->jsonResponder->invalidForm($form);
        }

        $entry = $this->passwordService->create($entry, (string) $form->get('plainPassword')->getData(), $this->currentUser());
        $message = $entry->isValidated()
            ? 'Le mot de passe a été créé.'
            : 'Le mot de passe a été créé et attend une validation.';

        return $this->jsonResponder->success($message, ['reload' => true], 201);
    }

    #[Route('/{id}/formulaire', name: 'app_password_form', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function form(PasswordEntry $entry): Response
    {
        $this->denyAccessUnlessGranted(PasswordEntryVoter::EDIT, $entry);
        $form = $this->createForm(PasswordEntryType::class, $entry, [
            'action' => $this->generateUrl('app_password_edit', ['id' => $entry->getId()]),
            'password_required' => false,
            'password_autocomplete' => 'off',
        ]);

        return $this->render('password/_form.html.twig', [
            'form' => $form,
            'title' => 'Modifier une entrée',
            'submit_label' => 'Enregistrer',
        ]);
    }

    #[Route('/{id}/modifier', name: 'app_password_edit', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function edit(PasswordEntry $entry, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(PasswordEntryVoter::EDIT, $entry);
        $form = $this->createForm(PasswordEntryType::class, $entry, [
            'password_required' => false,
            'password_autocomplete' => 'off',
        ]);
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->jsonResponder->invalidForm($form);
        }

        $this->passwordService->update($entry, $form->get('plainPassword')->getData(), $this->currentUser());

        return $this->jsonResponder->success('Les informations ont été mises à jour.', ['reload' => true]);
    }

    #[Route('/{id}/secret', name: 'app_password_reveal', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function reveal(PasswordEntry $entry, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(PasswordEntryVoter::VIEW, $entry);
        $this->assertCsrf($request, 'reveal_'.$entry->getId());
        $secret = $this->passwordService->reveal($entry, $this->currentUser());

        return $this->preventSecretCaching(
            $this->jsonResponder->success('Mot de passe prêt à être copié.', ['password' => $secret]),
        );
    }

    #[Route('/{id}/mot-de-passe', name: 'app_password_quick_edit', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function quickEdit(PasswordEntry $entry, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(PasswordEntryVoter::EDIT_PASSWORD, $entry);
        $payload = $request->toArray();
        $this->assertCsrfValue((string) ($payload['token'] ?? ''), 'password_update_'.$entry->getId());
        $this->passwordService->updatePasswordValue($entry, (string) ($payload['password'] ?? ''), $this->currentUser());

        return $this->jsonResponder->success('Le mot de passe a été modifié.');
    }

    #[Route('/{id}/valider', name: 'app_password_validate', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function validate(PasswordEntry $entry, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(PasswordEntryVoter::VALIDATE, $entry);
        $payload = $request->toArray();
        $this->assertCsrfValue((string) ($payload['token'] ?? ''), 'validate_password_'.$entry->getId());
        $this->passwordService->validate($entry, $this->currentUser());

        return $this->jsonResponder->success('Le mot de passe a été validé.', ['reload' => true]);
    }

    #[Route('/{id}/statut', name: 'app_password_toggle_status', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function toggleStatus(PasswordEntry $entry, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(PasswordEntryVoter::TOGGLE_STATUS, $entry);
        $payload = $request->toArray();
        $this->assertCsrfValue((string) ($payload['token'] ?? ''), 'toggle_password_'.$entry->getId());
        $active = $this->passwordService->toggleStatus($entry, $this->currentUser());

        return $this->jsonResponder->success(
            $active ? 'Le mot de passe a été réactivé.' : 'Le mot de passe a été désactivé.',
            ['reload' => true],
        );
    }

    #[Route('/{id}', name: 'app_password_delete', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    public function delete(PasswordEntry $entry, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(PasswordEntryVoter::DELETE, $entry);
        $payload = $request->toArray();
        $this->assertCsrfValue((string) ($payload['token'] ?? ''), 'delete_password_'.$entry->getId());
        $movedToTrash = $this->passwordService->delete($entry, $this->currentUser());
        if ($movedToTrash) {
            return $this->jsonResponder->success('Le mot de passe a ete deplace dans la corbeille.', ['reload' => true]);
        }

        return $this->jsonResponder->success('Le mot de passe a été supprimé.', ['reload' => true]);
    }

    private function currentUser(): User
    {
        $user = $this->getUser();
        \assert($user instanceof User);

        return $user;
    }

    private function assertCsrf(Request $request, string $id): void
    {
        $payload = $request->toArray();
        $this->assertCsrfValue((string) ($payload['token'] ?? ''), $id);
    }

    private function assertCsrfValue(string $token, string $id): void
    {
        if (!$this->isCsrfTokenValid($id, $token)) {
            throw new \DomainException('Jeton de sécurité invalide. Rechargez la page.');
        }
    }

    private function preventSecretCaching(Response $response): Response
    {
        $response->headers->set('Cache-Control', 'no-store, private');
        $response->headers->set('Pragma', 'no-cache');

        return $response;
    }
}
