<?php

namespace App\Controller;

use App\Entity\AppModule;
use App\Entity\User;
use App\Form\AppModuleType;
use App\Service\AppModuleService;
use App\Service\JsonResponder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/modules')]
#[IsGranted('ROLE_SUPER_ADMIN')]
final class AppModuleController extends AbstractController
{
    public function __construct(
        private readonly AppModuleService $moduleService,
        private readonly JsonResponder $jsonResponder,
    ) {
    }

    #[Route('', name: 'app_module_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('module/index.html.twig', [
            'modules' => $this->moduleService->getAll($this->currentUser()),
        ]);
    }

    #[Route('/{id}/formulaire', name: 'app_module_form', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function form(AppModule $module): Response
    {
        $form = $this->createForm(AppModuleType::class, $module, [
            'action' => $this->generateUrl('app_module_edit', ['id' => $module->getId()]),
        ]);

        return $this->render('module/_form.html.twig', [
            'form' => $form,
            'title' => 'Modifier un module',
            'submit_label' => 'Enregistrer',
        ]);
    }

    #[Route('/{id}/modifier', name: 'app_module_edit', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function edit(AppModule $module, Request $request): JsonResponse
    {
        $form = $this->createForm(AppModuleType::class, $module);
        $form->handleRequest($request);
        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->jsonResponder->invalidForm($form);
        }

        $this->moduleService->save($module, $this->currentUser());

        return $this->jsonResponder->success('Le module a été modifié.', ['reload' => true]);
    }

    #[Route('/{id}', name: 'app_module_delete', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    public function delete(AppModule $module, Request $request): JsonResponse
    {
        $payload = $request->toArray();
        if (!$this->isCsrfTokenValid('delete_module_'.$module->getId(), (string) ($payload['token'] ?? ''))) {
            throw new \DomainException('Jeton de sécurité invalide. Rechargez la page.');
        }

        $this->moduleService->delete($module, $this->currentUser());

        return $this->jsonResponder->success('Le module a été supprimé.', ['reload' => true]);
    }

    private function currentUser(): User
    {
        $user = $this->getUser();
        \assert($user instanceof User);

        return $user;
    }
}
