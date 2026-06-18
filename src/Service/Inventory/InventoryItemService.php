<?php

namespace App\Service\Inventory;

use App\Entity\InventoryAttachment;
use App\Entity\InventoryCategory;
use App\Entity\InventoryItem;
use App\Entity\InventoryLocation;
use App\Entity\InventoryMovement;
use App\Entity\InventorySite;
use App\Entity\User;
use App\Repository\InventoryCategoryRepository;
use App\Repository\InventoryItemRepository;
use App\Service\Trash\TrashService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final readonly class InventoryItemService
{
    public function __construct(
        private InventoryItemRepository $repository,
        private InventoryCategoryRepository $categoryRepository,
        private EntityManagerInterface $entityManager,
        private InventoryAccessService $access,
        private InventoryFileService $fileService,
        private TrashService $trashService,
        private InventoryMovementService $movementService,
    ) {
    }

    /** @param array<string, mixed> $filters */
    public function search(User $actor, array $filters = [], int $page = 1): array
    {
        $this->assertAccess($actor);

        return $this->repository->searchVisible($actor, $this->access->canViewAll($actor), $filters, $page);
    }

    public function nextReference(): string
    {
        $today = new \DateTimeImmutable('today');
        $prefix = 'INV-'.$today->format('Ymd');

        return sprintf('%s-%03d', $prefix, $this->repository->nextReferenceNumber($prefix));
    }

    public function create(InventoryItem $item, User $actor, ?UploadedFile $file = null, string $fileType = 'document', ?string $categoryName = null): InventoryItem
    {
        if (!$this->access->canCreate($actor)) {
            throw new AccessDeniedException();
        }

        $item->setCategory($this->resolveCategory($categoryName));
        $this->prepare($item);
        $item->setCreatedBy($actor);

        if (!$item->getResponsibleUser() instanceof User && !$this->access->canViewAll($actor)) {
            $item->setResponsibleUser($actor);
        }

        if ($file instanceof UploadedFile) {
            $this->fileService->store($item, $file, $fileType);
        }

        $this->entityManager->persist($item);
        $this->entityManager->flush();

        return $item;
    }

    public function update(InventoryItem $item, User $actor, ?UploadedFile $file = null, string $fileType = 'document', ?string $categoryName = null): InventoryItem
    {
        if (!$this->access->canEditItem($actor, $item)) {
            throw new AccessDeniedException();
        }

        $item->setCategory($this->resolveCategory($categoryName));
        $this->prepare($item);
        if ($file instanceof UploadedFile) {
            $this->fileService->store($item, $file, $fileType);
        }

        $this->entityManager->flush();

        return $item;
    }

    public function delete(InventoryItem $item, User $actor): bool
    {
        if (!$this->access->canDeleteItem($actor, $item)) {
            throw new AccessDeniedException();
        }

        if (!$this->access->isSuperAdmin($actor)) {
            $this->trashService->moveToTrash($item, $actor);

            return true;
        }

        $this->fileService->deleteFilesForItem($item);
        $this->entityManager->remove($item);
        $this->entityManager->flush();

        return false;
    }

    public function archive(InventoryItem $item, User $actor): bool
    {
        if (!$this->access->canEditItem($actor, $item)) {
            throw new AccessDeniedException();
        }

        $item->setIsActive(!$item->isActive());
        $this->entityManager->flush();

        return $item->isActive();
    }

    public function deleteAttachment(InventoryAttachment $attachment, User $actor): void
    {
        $item = $attachment->getItem();
        if (!$item instanceof InventoryItem || !$this->access->canEditItem($actor, $item)) {
            throw new AccessDeniedException();
        }

        $this->fileService->delete($attachment);
        $this->entityManager->remove($attachment);
        $this->entityManager->flush();
    }

    public function adjustQuantity(InventoryItem $item, int $quantity, User $actor): InventoryMovement
    {
        if (!$this->access->canEditItem($actor, $item)) {
            throw new AccessDeniedException();
        }
        if ($quantity < 0) {
            throw new \DomainException('La quantite ne peut pas etre negative.');
        }

        return $this->movementService->create(
            (new InventoryMovement())
                ->setItem($item)
                ->setMovementType('adjustment')
                ->setQuantity($quantity)
                ->setReason('Ajustement rapide depuis la liste du materiel'),
            $actor,
        );
    }

    public function move(
        InventoryItem $item,
        ?InventorySite $site,
        ?InventoryLocation $location,
        string $logisticsStatus,
        User $actor,
    ): InventoryMovement {
        if (!$this->access->canEditItem($actor, $item)) {
            throw new AccessDeniedException();
        }
        if (!$site instanceof InventorySite && !$location instanceof InventoryLocation) {
            throw new \DomainException('Selectionnez un site ou un emplacement de destination.');
        }

        $destinationSite = $location?->getSite() ?? $site;
        if ($destinationSite?->getId() === $item->getSite()?->getId()
            && (!$location instanceof InventoryLocation || $location->getId() === $item->getLocation()?->getId())) {
            throw new \DomainException('Le materiel se trouve deja a cette destination.');
        }

        $movement = $this->movementService->create(
            (new InventoryMovement())
                ->setItem($item)
                ->setMovementType('transfer')
                ->setQuantity(max(1, $item->getQuantity()))
                ->setToSite($destinationSite)
                ->setToLocation($location)
                ->setReason('Deplacement rapide du materiel'),
            $actor,
        );
        $item->setLogisticsStatus($logisticsStatus);
        $this->entityManager->flush();

        return $movement;
    }

    public function updateLogisticsStatus(InventoryItem $item, string $status, User $actor): void
    {
        if (!$this->access->canEditItem($actor, $item)) {
            throw new AccessDeniedException();
        }

        $item->setLogisticsStatus($status);
        $this->entityManager->flush();
    }

    private function prepare(InventoryItem $item): void
    {
        if (!$item->getReference()) {
            $item->setReference($this->nextReference());
        }

        if ($item->getQuantity() < 0) {
            throw new \DomainException('La quantite ne peut pas etre negative.');
        }

        if ($item->getAvailableQuantity() > $item->getQuantity()) {
            throw new \DomainException('La quantite disponible ne peut pas depasser la quantite totale.');
        }
    }

    private function resolveCategory(?string $categoryName): ?InventoryCategory
    {
        $categoryName = trim((string) $categoryName);
        if ($categoryName === '') {
            return null;
        }

        $category = $this->categoryRepository->findOneByNameInsensitive($categoryName);
        if ($category instanceof InventoryCategory) {
            $category->setIsActive(true);

            return $category;
        }

        $category = (new InventoryCategory())->setName($categoryName);
        $this->entityManager->persist($category);

        return $category;
    }

    private function assertAccess(User $actor): void
    {
        if (!$this->access->canAccess($actor)) {
            throw new AccessDeniedException();
        }
    }
}
