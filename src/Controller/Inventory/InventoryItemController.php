<?php

namespace App\Controller\Inventory;

use App\Entity\InventoryAttachment;
use App\Entity\InventoryItem;
use App\Entity\InventoryLocation;
use App\Entity\InventorySite;
use App\Entity\User;
use App\Form\InventoryItemType;
use App\Repository\ContactRepository;
use App\Repository\InventoryCategoryRepository;
use App\Repository\InventoryLocationRepository;
use App\Repository\InventoryMovementRepository;
use App\Repository\InventorySiteRepository;
use App\Security\Voter\InventoryVoter;
use App\Service\SecurityAccessService;
use App\Service\Inventory\InventoryFileService;
use App\Service\Inventory\InventoryItemService;
use App\Service\Inventory\InventoryRequestService;
use App\Service\JsonResponder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/inventaire/materiel')]
#[IsGranted('ROLE_USER')]
final class InventoryItemController extends AbstractController
{
    public function __construct(
        private readonly InventoryItemService $itemService,
        private readonly InventoryFileService $fileService,
        private readonly InventoryCategoryRepository $categoryRepository,
        private readonly InventorySiteRepository $siteRepository,
        private readonly InventoryLocationRepository $locationRepository,
        private readonly InventoryMovementRepository $movementRepository,
        private readonly ContactRepository $contactRepository,
        private readonly SecurityAccessService $securityAccess,
        private readonly InventoryRequestService $requestService,
        private readonly JsonResponder $jsonResponder,
    ) {
    }

    #[Route('', name: 'app_inventory_item_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $this->denyAccessUnlessGranted(InventoryVoter::ACCESS);
        $filters = $this->filters($request);
        $result = $this->itemService->search($this->currentUser(), $filters, (int) $request->query->get('page', 1));

        return $this->render('inventory/item/index.html.twig', [
            'result' => $result,
            'filters' => $filters,
        ]);
    }

    #[Route('/recherche', name: 'app_inventory_item_search', methods: ['GET'])]
    public function search(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(InventoryVoter::ACCESS);
        $filters = $this->filters($request);
        $result = $this->itemService->search($this->currentUser(), $filters, (int) $request->query->get('page', 1));

        return $this->jsonResponder->success('Recherche mise à jour.', [
            'html' => $this->renderView('inventory/item/_table.html.twig', ['result' => $result, 'filters' => $filters]),
            'count' => $result['total'],
        ]);
    }

    #[Route('/nouveau', name: 'app_inventory_item_new', methods: ['GET'])]
    public function new(Request $request): Response
    {
        $this->denyAccessUnlessGranted(InventoryVoter::ITEM_CREATE);
        $parameters = [
            'form' => $this->buildForm((new InventoryItem())->setReference($this->itemService->nextReference()), 'app_inventory_item_create'),
            'category_suggestions' => $this->categorySuggestions(),
        ];

        if ($request->isXmlHttpRequest()) {
            return $this->render('inventory/item/_form_modal.html.twig', $parameters + [
                'title' => 'Ajouter un matériel',
                'submit_label' => 'Créer',
                'item' => null,
            ]);
        }

        return $this->render('inventory/item/new.html.twig', $parameters);
    }

    #[Route('/creer', name: 'app_inventory_item_create', methods: ['POST'])]
    public function create(Request $request): Response
    {
        $this->denyAccessUnlessGranted(InventoryVoter::ITEM_CREATE);
        $item = (new InventoryItem())->setReference($this->itemService->nextReference());
        $form = $this->buildForm($item, 'app_inventory_item_create');
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            if (!$request->isXmlHttpRequest()) {
                return $this->render('inventory/item/new.html.twig', ['form' => $form, 'category_suggestions' => $this->categorySuggestions()], new Response(status: 422));
            }

            return $this->jsonResponder->invalidForm($form);
        }

        try {
            $this->itemService->create($item, $this->currentUser(), $this->uploadedFile($form), (string) $form->get('attachmentType')->getData(), (string) $form->get('categoryName')->getData());
        } catch (\DomainException $exception) {
            if (!$request->isXmlHttpRequest()) {
                $this->addFlash('danger', $exception->getMessage());

                return $this->render('inventory/item/new.html.twig', ['form' => $form, 'category_suggestions' => $this->categorySuggestions()], new Response(status: 422));
            }

            return $this->jsonResponder->error($exception->getMessage(), [], 422);
        }

        if (!$request->isXmlHttpRequest()) {
            $this->addFlash('success', 'Le matériel a été créé.');

            return $this->redirectToRoute('app_inventory_item_index');
        }

        return $this->jsonResponder->success('Le matériel a été créé.', [
            'closeModal' => true,
            'refreshRegion' => 'inventory-items',
        ], 201);
    }

    #[Route('/{id}/consulter', name: 'app_inventory_item_view', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function view(InventoryItem $item, Request $request): Response
    {
        $this->denyAccessUnlessGranted(InventoryVoter::ITEM_VIEW, $item);

        if (!$request->isXmlHttpRequest()) {
            return $this->render('inventory/item/show.html.twig', ['item' => $item]);
        }

        return $this->render('inventory/item/_details_modal.html.twig', ['item' => $item]);
    }

    #[Route('/{id}/historique-mouvements', name: 'app_inventory_item_movement_history', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function movementHistory(InventoryItem $item): Response
    {
        $this->denyAccessUnlessGranted(InventoryVoter::ITEM_VIEW, $item);

        return $this->render('inventory/item/_movement_history_modal.html.twig', [
            'item' => $item,
            'movements' => $this->movementRepository->historyForItem($item),
        ]);
    }

    #[Route('/{id}/formulaire', name: 'app_inventory_item_form', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function form(InventoryItem $item): Response
    {
        $this->denyAccessUnlessGranted(InventoryVoter::ITEM_EDIT, $item);

        return $this->render('inventory/item/_form_modal.html.twig', [
            'form' => $this->buildForm($item, 'app_inventory_item_edit', ['id' => $item->getId()]),
            'title' => sprintf('Modifier %s', $item->getReference()),
            'submit_label' => 'Enregistrer',
            'item' => $item,
            'category_suggestions' => $this->categorySuggestions(),
        ]);
    }

    #[Route('/contacts/recherche', name: 'app_inventory_contact_search', methods: ['GET'])]
    public function contactSearch(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(InventoryVoter::ACCESS);
        $contacts = $this->contactRepository->searchVisibleWithMobile(
            $this->currentUser(),
            $this->securityAccess->isAdmin($this->currentUser()),
            (string) $request->query->get('q', ''),
            2,
        );

        return $this->jsonResponder->success('Contacts trouvés.', [
            'contacts' => array_map(static fn ($contact): array => [
                'id' => $contact->getId(),
                'name' => $contact->getContactPersonName() ?: $contact->getFullName(),
                'company' => $contact->getFullName(),
                'phone' => $contact->getMobileNumbers()[0] ?? null,
            ], $contacts),
        ]);
    }

    #[Route('/{id}/modification-rapide/{field}', name: 'app_inventory_item_quick_form', requirements: ['id' => '\d+', 'field' => 'quantity|site|location|logistics|move|inventory'], methods: ['GET'])]
    public function quickForm(InventoryItem $item, string $field): Response
    {
        $this->denyAccessUnlessGranted(InventoryVoter::ITEM_EDIT, $item);

        return $this->render('inventory/item/_quick_edit_modal.html.twig', [
            'item' => $item,
            'field' => $field,
            'sites' => $this->siteRepository->activeList(),
            'locations' => $this->locationRepository->activeList(),
        ]);
    }

    #[Route('/{id}/modification-rapide/{field}', name: 'app_inventory_item_quick_update', requirements: ['id' => '\d+', 'field' => 'quantity|site|location|logistics|move|inventory'], methods: ['POST'])]
    public function quickUpdate(InventoryItem $item, string $field, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(InventoryVoter::ITEM_EDIT, $item);
        $this->assertCsrf((string) $request->request->get('token'), 'quick_inventory_item_'.$item->getId());

        try {
            if ($field === 'quantity') {
                $this->itemService->adjustQuantity($item, (int) $request->request->get('quantity'), $this->currentUser());
                $message = 'La quantité a été ajustée et tracée dans les mouvements.';
            } elseif ($field === 'logistics') {
                $this->itemService->updateLogisticsStatus($item, (string) $request->request->get('logisticsStatus'), $this->currentUser());
                $message = 'Le suivi logistique a été mis à jour.';
            } elseif ($field === 'inventory') {
                $this->requestService->requestInventory(
                    $item,
                    $this->currentUser(),
                    (string) $request->request->get('notes', ''),
                );
                $message = 'La demande d’inventaire a été ajoutée aux validations.';
            } else {
                $site = $this->activeSite((int) $request->request->get('site'));
                $location = $this->activeLocation((int) $request->request->get('location'));
                $this->requestService->requestTransfer(
                    $item,
                    (int) $request->request->get('quantity', $item->getQuantity()),
                    $site,
                    $location,
                    $item->getLogisticsStatus() === 'legacy_remaining' ? 'transferred_new' : $item->getLogisticsStatus(),
                    $this->currentUser(),
                    (string) $request->request->get('notes', ''),
                );
                $message = 'La demande de transport a été ajoutée aux validations.';
            }
        } catch (\DomainException $exception) {
            return $this->jsonResponder->error($exception->getMessage(), [], 422);
        }

        return $this->jsonResponder->success($message, [
            'closeModal' => true,
            'refreshRegion' => 'inventory-items',
        ]);
    }

    #[Route('/{id}/action-whatsapp', name: 'app_inventory_item_whatsapp_form', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function whatsappForm(InventoryItem $item): Response
    {
        $this->denyAccessUnlessGranted(InventoryVoter::ITEM_VIEW, $item);

        return $this->render('inventory/item/_whatsapp_modal.html.twig', [
            'item' => $item,
            'sites' => $this->siteRepository->activeList(),
        ]);
    }

    #[Route('/{id}/action-whatsapp', name: 'app_inventory_item_whatsapp_prepare', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function whatsappPrepare(InventoryItem $item, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(InventoryVoter::ITEM_VIEW, $item);
        $payload = $request->toArray();
        $this->assertCsrf((string) ($payload['token'] ?? ''), 'whatsapp_inventory_item_'.$item->getId());

        $contact = $this->contactRepository->findVisibleOne(
            $this->currentUser(),
            $this->securityAccess->isAdmin($this->currentUser()),
            (int) ($payload['contactId'] ?? 0),
        );
        $phone = $contact?->getMobileNumbers()[0] ?? null;
        if (!$contact || !$phone) {
            return $this->jsonResponder->error('Sélectionnez un contact disposant d’un numéro de portable.', [], 422);
        }

        $action = (string) ($payload['action'] ?? '');
        $destination = null;
        $quantity = max(1, (int) ($payload['quantity'] ?? $item->getQuantity()));
        $notes = (string) ($payload['notes'] ?? '');
        if ($action === 'transport') {
            $destination = $this->activeSite((int) ($payload['destinationSiteId'] ?? 0));
            if (!$destination instanceof InventorySite || $destination->getId() === $item->getSite()?->getId()) {
                return $this->jsonResponder->error('Sélectionnez un autre site de destination.', [], 422);
            }
        } elseif ($action !== 'inventory') {
            return $this->jsonResponder->error('Sélectionnez une action à effectuer.', [], 422);
        }

        $message = $this->whatsappMessage($item, $action, $destination, $quantity);

        $whatsappNumber = $this->whatsappNumber($phone);
        if (strlen($whatsappNumber) < 8) {
            return $this->jsonResponder->error('Le numéro de portable du contact est invalide pour WhatsApp.', [], 422);
        }

        try {
            if ($action === 'transport') {
                $this->requestService->requestTransfer(
                    $item,
                    $quantity,
                    $destination,
                    null,
                    $item->getLogisticsStatus() === 'legacy_remaining' ? 'transferred_new' : $item->getLogisticsStatus(),
                    $this->currentUser(),
                    $notes,
                );
            } else {
                $this->requestService->requestInventory($item, $this->currentUser(), $notes);
            }
        } catch (\DomainException $exception) {
            return $this->jsonResponder->error($exception->getMessage(), [], 422);
        }

        return $this->jsonResponder->success('La consigne WhatsApp est prête et la demande a été enregistrée.', [
            'url' => sprintf('https://wa.me/%s?text=%s', $whatsappNumber, rawurlencode($message)),
        ]);
    }

    #[Route('/{id}/modifier', name: 'app_inventory_item_edit', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function edit(InventoryItem $item, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(InventoryVoter::ITEM_EDIT, $item);
        $form = $this->buildForm($item, 'app_inventory_item_edit', ['id' => $item->getId()]);
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->jsonResponder->invalidForm($form);
        }

        try {
            $this->itemService->update($item, $this->currentUser(), $this->uploadedFile($form), (string) $form->get('attachmentType')->getData(), (string) $form->get('categoryName')->getData());
        } catch (\DomainException $exception) {
            return $this->jsonResponder->error($exception->getMessage(), [], 422);
        }

        return $this->jsonResponder->success('Le matériel a été modifié.', [
            'closeModal' => true,
            'refreshRegion' => 'inventory-items',
        ]);
    }

    #[Route('/{id}/archive', name: 'app_inventory_item_archive', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function archive(InventoryItem $item, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(InventoryVoter::ITEM_EDIT, $item);
        $payload = $request->toArray();
        $this->assertCsrf((string) ($payload['token'] ?? ''), 'archive_inventory_item_'.$item->getId());
        $active = $this->itemService->archive($item, $this->currentUser());

        return $this->jsonResponder->success($active ? 'Le matériel a été réactivé.' : 'Le matériel a été archivé.', [
            'closeModal' => true,
            'refreshRegion' => 'inventory-items',
        ]);
    }

    #[Route('/{id}', name: 'app_inventory_item_delete', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    public function delete(InventoryItem $item, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(InventoryVoter::ITEM_DELETE, $item);
        $payload = $request->toArray();
        $this->assertCsrf((string) ($payload['token'] ?? ''), 'delete_inventory_item_'.$item->getId());
        $movedToTrash = $this->itemService->delete($item, $this->currentUser());

        return $this->jsonResponder->success($movedToTrash ? 'Le matériel a été déplacé dans la corbeille.' : 'Le matériel a été supprimé.', [
            'closeModal' => true,
            'refreshRegion' => 'inventory-items',
        ]);
    }

    #[Route('/pieces-jointes/{id}/voir', name: 'app_inventory_attachment_view', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function viewAttachment(InventoryAttachment $attachment): BinaryFileResponse
    {
        $this->denyAccessUnlessGranted(InventoryVoter::ATTACHMENT_VIEW, $attachment);
        $response = new BinaryFileResponse($this->fileService->file($attachment));
        $response->headers->set('Content-Type', $attachment->getMimeType());
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_INLINE, $attachment->getOriginalFileName() ?? 'piece-jointe');

        return $response;
    }

    #[Route('/pieces-jointes/{id}', name: 'app_inventory_attachment_delete', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    public function deleteAttachment(InventoryAttachment $attachment, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(InventoryVoter::ATTACHMENT_VIEW, $attachment);
        $payload = $request->toArray();
        $this->assertCsrf((string) ($payload['token'] ?? ''), 'delete_inventory_attachment_'.$attachment->getId());
        $this->itemService->deleteAttachment($attachment, $this->currentUser());

        return $this->jsonResponder->success('La pièce jointe a été supprimée.', [
            'closeModal' => true,
            'refreshRegion' => 'inventory-items',
        ]);
    }

    /** @param array<string, int|string|null> $parameters */
    private function buildForm(InventoryItem $item, string $route, array $parameters = []): \Symfony\Component\Form\FormInterface
    {
        return $this->createForm(InventoryItemType::class, $item, [
            'action' => $this->generateUrl($route, $parameters),
            'allowed_mime_types' => $this->fileService->allowedMimeTypes(),
            'max_file_size' => $this->fileService->maxFileSize(),
            'category_name' => $item->getCategory()?->getName() ?? '',
            'category_suggestions' => $this->categorySuggestions(),
        ]);
    }

    /** @return list<string> */
    private function categorySuggestions(): array
    {
        return array_map(static fn ($category): string => (string) $category->getName(), $this->categoryRepository->activeList());
    }

    /** @return array<string, mixed> */
    private function filters(Request $request): array
    {
        return [
            'q' => (string) $request->query->get('q', ''),
            'status' => (string) $request->query->get('status', ''),
            'condition' => (string) $request->query->get('condition', ''),
            'category' => (int) $request->query->get('category', 0),
            'site' => (int) $request->query->get('site', 0),
            'responsible' => (int) $request->query->get('responsible', 0),
            'logisticsStatus' => (string) $request->query->get('logisticsStatus', ''),
            'active' => (string) $request->query->get('active', 'active'),
        ];
    }

    private function activeSite(int $id): ?InventorySite
    {
        $site = $id > 0 ? $this->siteRepository->find($id) : null;

        return $site instanceof InventorySite && $site->isActive() ? $site : null;
    }

    private function activeLocation(int $id): ?InventoryLocation
    {
        $location = $id > 0 ? $this->locationRepository->find($id) : null;

        return $location instanceof InventoryLocation
            && $location->isActive()
            && $location->getSite()?->isActive()
            ? $location
            : null;
    }

    private function whatsappMessage(InventoryItem $item, string $action, ?InventorySite $destination, ?int $quantity = null): string
    {
        $quantity ??= $item->getQuantity();
        $lines = [
            'Bonjour,',
            '',
            $action === 'transport'
                ? sprintf('Action demandée : transporter %d %s vers %s.', $quantity, $item->getUnit(), $destination?->getName())
                : 'Action demandée : faire l’inventaire de ce matériel et confirmer sa quantité et son état.',
            '',
            sprintf('Matériel : %s', $item->getName()),
            sprintf('Référence : %s', $item->getReference()),
            $action === 'transport'
                ? sprintf('Quantité demandée : %d %s', $quantity, $item->getUnit())
                : sprintf('Quantité actuelle fiche : %d %s', $item->getQuantity(), $item->getUnit()),
            $action === 'transport'
                ? sprintf('Quantité totale fiche : %d %s', $item->getQuantity(), $item->getUnit())
                : 'Merci de confirmer la quantité constatée sur place.',
            sprintf('Site actuel : %s', $item->getSite()?->getName() ?? 'Non renseigné'),
            sprintf('Emplacement actuel : %s', $item->getLocation()?->getName() ?? 'Non renseigné'),
            sprintf('Suivi : %s', $item->getLogisticsStatusLabel()),
            sprintf('Fiche complete : %s', $this->generateUrl('app_inventory_item_view', ['id' => $item->getId()], UrlGeneratorInterface::ABSOLUTE_URL)),
        ];

        foreach ($item->getAttachments() as $attachment) {
            if ($attachment->isImage()) {
                $lines[] = sprintf('Photo : %s', $this->generateUrl('app_inventory_attachment_view', ['id' => $attachment->getId()], UrlGeneratorInterface::ABSOLUTE_URL));
                break;
            }
        }

        return implode("\n", $lines);
    }

    private function whatsappNumber(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        } elseif (str_starts_with($digits, '0')) {
            $digits = '212'.substr($digits, 1);
        }

        return $digits;
    }

    private function uploadedFile(\Symfony\Component\Form\FormInterface $form): ?UploadedFile
    {
        $file = $form->get('file')->getData();

        return $file instanceof UploadedFile ? $file : null;
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
