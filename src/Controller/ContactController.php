<?php

namespace App\Controller;

use App\Entity\Contact;
use App\Entity\User;
use App\Form\ContactType;
use App\Security\Voter\ContactVoter;
use App\Security\Voter\ModuleAccessVoter;
use App\Service\ContactService;
use App\Service\JsonResponder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/contacts')]
#[IsGranted('ROLE_USER')]
final class ContactController extends AbstractController
{
    public function __construct(
        private readonly ContactService $contactService,
        private readonly JsonResponder $jsonResponder,
    ) {
    }

    #[Route('', name: 'app_contact_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $this->denyAccessUnlessGranted(ModuleAccessVoter::ACCESS, 'contacts');
        $createForm = $this->buildForm(new Contact(), 'app_contact_create');
        $filters = [
            'type' => trim((string) $request->query->get('type', '')),
            'city' => trim((string) $request->query->get('city', '')),
        ];

        return $this->render('contact/index.html.twig', [
            'contacts' => $this->contactService->getVisibleContacts($this->currentUser(), $filters),
            'create_form' => $createForm,
            'type_suggestions' => $this->contactService->getTypeSuggestions(),
            'city_suggestions' => $this->contactService->getCitySuggestions(),
            'filters' => $filters,
        ]);
    }

    #[Route('/creer', name: 'app_contact_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(ContactVoter::CREATE);
        $contact = new Contact();
        $form = $this->buildForm($contact, 'app_contact_create');
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->jsonResponder->invalidForm($form);
        }

        $this->contactService->create($contact, $this->currentUser());

        return $this->jsonResponder->success('Le contact a été créé.', ['reload' => true], 201);
    }

    #[Route('/{id}/formulaire', name: 'app_contact_form', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function form(Contact $contact): Response
    {
        $this->denyAccessUnlessGranted(ContactVoter::EDIT, $contact);
        $form = $this->buildForm($contact, 'app_contact_edit', ['id' => $contact->getId()]);

        return $this->render('contact/_form.html.twig', [
            'form' => $form,
            'title' => sprintf('Modifier %s', $contact->getFullName()),
            'submit_label' => 'Enregistrer',
            'type_suggestions' => $this->contactService->getTypeSuggestions(),
        ]);
    }

    #[Route('/{id}/modifier', name: 'app_contact_edit', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function edit(Contact $contact, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(ContactVoter::EDIT, $contact);
        $form = $this->buildForm($contact, 'app_contact_edit', ['id' => $contact->getId()]);
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->jsonResponder->invalidForm($form);
        }

        $this->contactService->update($contact, $this->currentUser());

        return $this->jsonResponder->success('Le contact a été mis à jour.', ['reload' => true]);
    }

    #[Route('/{id}', name: 'app_contact_delete', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    public function delete(Contact $contact, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(ContactVoter::DELETE, $contact);
        $payload = $request->toArray();
        if (!$this->isCsrfTokenValid('delete_contact_'.$contact->getId(), (string) ($payload['token'] ?? ''))) {
            throw new \DomainException('Jeton de sécurité invalide. Rechargez la page.');
        }

        $this->contactService->delete($contact, $this->currentUser());

        return $this->jsonResponder->success('Le contact a été supprimé.', ['reload' => true]);
    }

    /** @param array<string, int|string|null> $routeParameters */
    private function buildForm(Contact $contact, string $route, array $routeParameters = []): \Symfony\Component\Form\FormInterface
    {
        return $this->createForm(ContactType::class, $contact, [
            'action' => $this->generateUrl($route, $routeParameters),
            'type_suggestions' => $this->contactService->getTypeSuggestions(),
        ]);
    }

    private function currentUser(): User
    {
        $user = $this->getUser();
        \assert($user instanceof User);

        return $user;
    }
}
