<?php

namespace App\Controller\Inventory;

use App\Entity\InventoryLocation;
use App\Entity\InventorySite;
use App\Form\InventoryLocationType;
use App\Form\InventorySiteType;
use App\Repository\InventoryItemRepository;
use App\Repository\InventoryLocationRepository;
use App\Repository\InventorySiteRepository;
use App\Security\Voter\InventoryVoter;
use App\Service\Inventory\InventoryTaxonomyService;
use App\Service\JsonResponder;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/inventaire')]
#[IsGranted('ROLE_USER')]
final class InventoryTaxonomyController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly InventorySiteRepository $siteRepository,
        private readonly InventoryLocationRepository $locationRepository,
        private readonly InventoryItemRepository $itemRepository,
        private readonly InventoryTaxonomyService $taxonomyService,
        private readonly JsonResponder $jsonResponder,
    ) {
    }

    #[Route('/sites-emplacements', name: 'app_inventory_location_index', methods: ['GET'])]
    public function sitesAndLocations(): Response
    {
        $this->denyAccessUnlessGranted(InventoryVoter::ACCESS);

        return $this->render('inventory/location/index.html.twig', $this->pageData());
    }

    #[Route('/sites-emplacements/contenu', name: 'app_inventory_location_content', methods: ['GET'])]
    public function content(): JsonResponse
    {
        $this->denyAccessUnlessGranted(InventoryVoter::ACCESS);

        return $this->jsonResponder->success('Sites et emplacements actualises.', [
            'html' => $this->renderView('inventory/location/_content.html.twig', $this->pageData()),
        ]);
    }

    /** @return array<string, mixed> */
    private function pageData(): array
    {
        return [
            'sites' => $this->siteRepository->activeList(),
            'locations' => $this->locationRepository->activeList(),
            'site_item_counts' => $this->itemRepository->countActiveBySite(),
            'location_item_counts' => $this->itemRepository->countActiveByLocation(),
            'site_form' => $this->createForm(InventorySiteType::class, new InventorySite(), [
                'action' => $this->generateUrl('app_inventory_site_create'),
            ]),
        ];
    }

    #[Route('/sites/creer', name: 'app_inventory_site_create', methods: ['POST'])]
    public function createSite(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(InventoryVoter::ACCESS);
        $site = new InventorySite();
        $form = $this->createForm(InventorySiteType::class, $site, [
            'action' => $this->generateUrl('app_inventory_site_create'),
        ]);
        $form->handleRequest($request);
        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->jsonResponder->invalidForm($form);
        }

        $this->entityManager->persist($site);
        $this->entityManager->flush();

        return $this->jsonResponder->success('Le site a ete cree.', [
            'closeModal' => true,
            'refreshRegion' => 'inventory-taxonomy',
        ], 201);
    }

    #[Route('/emplacements/creer', name: 'app_inventory_location_create', methods: ['POST'])]
    public function createLocation(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(InventoryVoter::ACCESS);
        $location = new InventoryLocation();
        $site = $this->activeSite((int) $request->query->get('site', 0));
        if ($site instanceof InventorySite) {
            $location->setSite($site);
        }

        $form = $this->buildLocationForm($location, $site);
        $form->handleRequest($request);
        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->jsonResponder->invalidForm($form);
        }

        $this->entityManager->persist($location);
        $this->entityManager->flush();

        return $this->jsonResponder->success('L emplacement a ete cree.', [
            'closeModal' => true,
            'refreshRegion' => 'inventory-taxonomy',
        ], 201);
    }

    #[Route('/emplacements/nouveau', name: 'app_inventory_location_new', methods: ['GET'])]
    public function newLocation(Request $request): Response
    {
        $this->denyAccessUnlessGranted(InventoryVoter::ACCESS);
        $location = new InventoryLocation();
        $site = $this->activeSite((int) $request->query->get('site', 0));
        if ($site instanceof InventorySite) {
            $location->setSite($site);
        }

        return $this->render('inventory/location/_location_form_modal.html.twig', [
            'form' => $this->buildLocationForm($location, $site),
            'site' => $site,
        ]);
    }

    #[Route('/sites/{id}/supprimer', name: 'app_inventory_site_delete_form', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function deleteSiteForm(InventorySite $site): Response
    {
        $this->denyAccessUnlessGranted(InventoryVoter::ACCESS);
        $count = $this->itemRepository->countAttachedToSite($site);

        return $this->render('inventory/location/_delete_site_modal.html.twig', [
            'site' => $site,
            'item_count' => $count,
            'items' => $this->itemRepository->attachedToSite($site, 8),
            'destination_sites' => array_values(array_filter(
                $this->siteRepository->activeList(),
                static fn (InventorySite $destination): bool => $destination->getId() !== $site->getId(),
            )),
        ]);
    }

    #[Route('/sites/{id}/supprimer', name: 'app_inventory_site_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function deleteSite(InventorySite $site, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(InventoryVoter::ACCESS);
        $this->assertCsrf((string) $request->request->get('token'), 'delete_inventory_site_'.$site->getId());
        $destination = $this->activeSite((int) $request->request->get('destinationSite', 0));

        try {
            $movedItems = $this->taxonomyService->deleteSite($site, $destination, $this->currentUser());
        } catch (\DomainException $exception) {
            return $this->jsonResponder->error($exception->getMessage(), [], 422);
        }

        return $this->jsonResponder->success(sprintf(
            'Le site a ete supprime. %d materiel%s %s.',
            $movedItems,
            $movedItems > 1 ? 's' : '',
            $destination instanceof InventorySite ? 'ont ete rattaches au site selectionne' : 'ont ete detaches du site',
        ), [
            'closeModal' => true,
            'refreshRegion' => 'inventory-taxonomy',
        ]);
    }

    #[Route('/emplacements/{id}/supprimer', name: 'app_inventory_location_delete_form', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function deleteLocationForm(InventoryLocation $location): Response
    {
        $this->denyAccessUnlessGranted(InventoryVoter::ACCESS);
        $count = $this->itemRepository->countAttachedToLocation($location);

        return $this->render('inventory/location/_delete_location_modal.html.twig', [
            'location' => $location,
            'item_count' => $count,
            'items' => $this->itemRepository->attachedToLocation($location, 8),
            'destination_locations' => array_values(array_filter(
                $this->locationRepository->activeList(),
                static fn (InventoryLocation $destination): bool => $destination->getId() !== $location->getId(),
            )),
        ]);
    }

    #[Route('/emplacements/{id}/supprimer', name: 'app_inventory_location_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function deleteLocation(InventoryLocation $location, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(InventoryVoter::ACCESS);
        $this->assertCsrf((string) $request->request->get('token'), 'delete_inventory_location_'.$location->getId());
        $destination = $this->activeLocation((int) $request->request->get('destinationLocation', 0));

        try {
            $movedItems = $this->taxonomyService->deleteLocation($location, $destination, $this->currentUser());
        } catch (\DomainException $exception) {
            return $this->jsonResponder->error($exception->getMessage(), [], 422);
        }

        return $this->jsonResponder->success(sprintf(
            'L emplacement a ete supprime. %d materiel%s %s.',
            $movedItems,
            $movedItems > 1 ? 's' : '',
            $destination instanceof InventoryLocation ? 'ont ete rattaches a l emplacement selectionne' : 'gardent leur site sans emplacement',
        ), [
            'closeModal' => true,
            'refreshRegion' => 'inventory-taxonomy',
        ]);
    }

    private function buildLocationForm(InventoryLocation $location, ?InventorySite $lockedSite = null): \Symfony\Component\Form\FormInterface
    {
        return $this->createForm(InventoryLocationType::class, $location, [
            'action' => $this->generateUrl('app_inventory_location_create', $lockedSite instanceof InventorySite ? ['site' => $lockedSite->getId()] : []),
            'site_locked' => $lockedSite instanceof InventorySite,
        ]);
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

    private function assertCsrf(string $token, string $id): void
    {
        if (!$this->isCsrfTokenValid($id, $token)) {
            throw new \DomainException('Jeton de securite invalide. Rechargez la page.');
        }
    }

    private function currentUser(): \App\Entity\User
    {
        $user = $this->getUser();
        \assert($user instanceof \App\Entity\User);

        return $user;
    }
}
