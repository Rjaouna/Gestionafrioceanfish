<?php

namespace App\Controller;

use App\Entity\AppModule;
use App\Entity\User;
use App\Form\UserManagementType;
use App\Security\Voter\UserManagementVoter;
use App\Service\JsonResponder;
use App\Service\UserAccessDeliveryService;
use App\Service\UserManagementService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/utilisateurs')]
#[IsGranted('ROLE_ADMIN')]
final class UserManagementController extends AbstractController
{
    public function __construct(
        private readonly UserManagementService $userService,
        private readonly UserAccessDeliveryService $accessDeliveryService,
        private readonly JsonResponder $jsonResponder,
    ) {
    }

    #[Route('', name: 'app_user_index', methods: ['GET'])]
    public function index(): Response
    {
        $this->denyAccessUnlessGranted(UserManagementVoter::MANAGE);
        $form = $this->buildForm(new User(), true, 'app_user_create');

        return $this->render('user/index.html.twig', [
            'users' => $this->userService->getUsers($this->currentUser()),
            'create_form' => $form,
        ]);
    }

    #[Route('/creer', name: 'app_user_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(UserManagementVoter::MANAGE);
        $user = new User();
        $form = $this->buildForm($user, true, 'app_user_create');
        $form->handleRequest($request);
        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->jsonResponder->invalidForm($form);
        }

        try {
            $this->userService->create(
                $user,
                (string) $form->get('plainPassword')->getData(),
                $this->moduleIds($form->get('modules')->getData()),
                $this->currentUser(),
                $form->has('roles') ? (string) $form->get('roles')->getData() : null,
            );
        } catch (\DomainException $exception) {
            return $this->jsonResponder->error($exception->getMessage(), [], 422);
        }

        return $this->jsonResponder->success('L’utilisateur a été créé.', ['reload' => true], 201);
    }

    #[Route('/{id}/formulaire', name: 'app_user_form', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function form(User $user): Response
    {
        $this->denyAccessUnlessGranted(UserManagementVoter::MANAGE, $user);
        $form = $this->buildForm($user, false, 'app_user_edit', [
            'id' => $user->getId(),
        ]);

        return $this->render('user/_form_modal.html.twig', [
            'form' => $form,
            'title' => sprintf('Modifier %s', $user->getDisplayName()),
            'submit_label' => 'Enregistrer',
        ]);
    }

    #[Route('/{id}/modifier', name: 'app_user_edit', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function edit(User $user, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(UserManagementVoter::MANAGE, $user);
        $form = $this->buildForm($user, false, 'app_user_edit', ['id' => $user->getId()]);
        $form->handleRequest($request);
        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->jsonResponder->invalidForm($form);
        }

        try {
            $this->userService->update(
                $user,
                $form->get('plainPassword')->getData(),
                $this->moduleIds($form->get('modules')->getData()),
                $this->currentUser(),
                $form->has('roles') ? (string) $form->get('roles')->getData() : null,
            );
        } catch (\DomainException $exception) {
            return $this->jsonResponder->error($exception->getMessage(), [], 422);
        }

        return $this->jsonResponder->success('L’utilisateur a été modifié.', ['reload' => true]);
    }

    #[Route('/{id}/statut', name: 'app_user_toggle', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function toggle(User $user, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(UserManagementVoter::MANAGE, $user);
        $payload = $request->toArray();
        $this->assertCsrf((string) ($payload['token'] ?? ''), 'toggle_user_'.$user->getId());
        $active = $this->userService->toggleActive($user, $this->currentUser());

        return $this->jsonResponder->success(
            $active ? 'Le compte a été activé.' : 'Le compte a été désactivé.',
            ['active' => $active, 'reload' => true],
        );
    }

    #[Route('/{id}/envoyer-acces', name: 'app_user_send_accesses', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function sendAccesses(User $user, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(UserManagementVoter::MANAGE, $user);
        $payload = $request->toArray();
        $this->assertCsrf((string) ($payload['token'] ?? ''), 'send_user_accesses_'.$user->getId());

        try {
            $this->accessDeliveryService->sendPasswordAccesses($user, $this->currentUser());
        } catch (\DomainException $exception) {
            return $this->jsonResponder->error($exception->getMessage(), [], 422);
        } catch (TransportExceptionInterface) {
            return $this->jsonResponder->error('Impossible d’envoyer l’e-mail pour le moment. Vérifiez la configuration SMTP.', [], 500);
        }

        return $this->jsonResponder->success(sprintf(
            'Les informations de connexion ont été envoyées à %s.',
            $user->getDisplayName(),
        ));
    }

    #[Route('/{id}', name: 'app_user_delete', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    public function delete(User $user, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(UserManagementVoter::DELETE, $user);
        $payload = $request->toArray();
        $this->assertCsrf((string) ($payload['token'] ?? ''), 'delete_user_'.$user->getId());
        $this->userService->delete($user, $this->currentUser());

        return $this->jsonResponder->success('L’utilisateur a été supprimé.', ['reload' => true]);
    }

    /** @param array<string, int|string> $routeParameters */
    private function buildForm(User $user, bool $passwordRequired, string $route, array $routeParameters = []): \Symfony\Component\Form\FormInterface
    {
        $selectedModules = [];
        foreach ($user->getModuleAccesses() as $access) {
            if ($access->getModule() instanceof AppModule) {
                $selectedModules[] = $access->getModule();
            }
        }

        return $this->createForm(UserManagementType::class, $user, [
            'action' => $this->generateUrl($route, $routeParameters),
            'password_required' => $passwordRequired,
            'selected_modules' => $selectedModules,
            'can_manage_roles' => $this->isGranted('ROLE_SUPER_ADMIN'),
            'selected_role' => $this->primaryRole($user),
            'role_choices' => $this->roleChoicesFor($user),
        ]);
    }

    /** @return array<string, string> */
    private function roleChoicesFor(User $user): array
    {
        $currentUser = $this->currentUser();
        $allChoices = [
            'Utilisateur' => 'ROLE_USER',
            'Administrateur' => 'ROLE_ADMIN',
            'Super administrateur' => 'ROLE_SUPER_ADMIN',
        ];

        if ($user->getId() !== null && $currentUser->getId() === $user->getId()) {
            return array_filter(
                $allChoices,
                fn (string $role): bool => $role === $this->primaryRole($user),
            );
        }

        return $allChoices;
    }

    private function primaryRole(User $user): string
    {
        foreach (['ROLE_SUPER_ADMIN', 'ROLE_ADMIN'] as $role) {
            if (in_array($role, $user->getRoles(), true)) {
                return $role;
            }
        }

        return 'ROLE_USER';
    }

    /** @return list<int> */
    private function moduleIds(mixed $modules): array
    {
        $ids = [];
        foreach ($modules as $module) {
            if ($module instanceof AppModule && $module->getId() !== null) {
                $ids[] = $module->getId();
            }
        }

        return $ids;
    }

    private function currentUser(): User
    {
        $user = $this->getUser();
        \assert($user instanceof User);

        return $user;
    }

    private function assertCsrf(string $token, string $id): void
    {
        if (!$this->isCsrfTokenValid($id, $token)) {
            throw new \DomainException('Jeton de sécurité invalide. Rechargez la page.');
        }
    }
}
