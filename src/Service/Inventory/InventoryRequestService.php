<?php

namespace App\Service\Inventory;

use App\Entity\InventoryItem;
use App\Entity\InventoryLocation;
use App\Entity\InventoryMovement;
use App\Entity\InventoryRequest;
use App\Entity\InventorySite;
use App\Entity\User;
use App\Repository\InventoryRequestRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final readonly class InventoryRequestService
{
    public function __construct(
        private InventoryRequestRepository $repository,
        private EntityManagerInterface $entityManager,
        private InventoryAccessService $access,
        private InventoryMovementService $movementService,
        private InventoryItemService $itemService,
    ) {
    }

    /** @param array<string, mixed> $filters */
    public function search(User $actor, array $filters = []): array
    {
        if (!$this->access->canAccess($actor)) {
            throw new AccessDeniedException();
        }

        return $this->repository->searchVisible($actor, $this->access->canViewAll($actor), $filters);
    }

    public function countPending(User $actor): int
    {
        if (!$this->access->canAccess($actor)) {
            return 0;
        }

        return $this->repository->countPendingVisible($actor, $this->access->canViewAll($actor));
    }

    public function requestTransfer(
        InventoryItem $item,
        int $quantity,
        ?InventorySite $site,
        ?InventoryLocation $location,
        string $logisticsStatus,
        User $actor,
        ?string $notes = null,
    ): InventoryRequest {
        if (!$this->access->canEditItem($actor, $item)) {
            throw new AccessDeniedException();
        }

        $destinationSite = $location?->getSite() ?? $site;
        if (!$destinationSite instanceof InventorySite) {
            throw new \DomainException('Sélectionnez un site ou un emplacement de destination.');
        }

        if ($destinationSite->getId() === $item->getSite()?->getId()
            && (!$location instanceof InventoryLocation || $location->getId() === $item->getLocation()?->getId())) {
            throw new \DomainException('Le matériel se trouve déjà à cette destination.');
        }

        $this->assertTransferQuantity($item, $quantity);

        if (!in_array($logisticsStatus, InventoryItem::LOGISTICS_STATUSES, true)) {
            throw new \DomainException('État logistique invalide.');
        }

        $request = (new InventoryRequest())
            ->setRequestType('transfer')
            ->setStatus('pending')
            ->setItem($item)
            ->setRequestedQuantity($quantity)
            ->setFromSite($item->getSite())
            ->setFromLocation($item->getLocation())
            ->setToSite($destinationSite)
            ->setToLocation($location)
            ->setRequestedLogisticsStatus($logisticsStatus)
            ->setReason('Demande de transport')
            ->setNotes($notes)
            ->setCreatedBy($actor);

        $this->entityManager->persist($request);
        $this->entityManager->flush();

        return $request;
    }

    public function requestInventory(InventoryItem $item, User $actor, ?string $notes = null): InventoryRequest
    {
        if (!$this->access->canEditItem($actor, $item)) {
            throw new AccessDeniedException();
        }

        $request = (new InventoryRequest())
            ->setRequestType('inventory')
            ->setStatus('pending')
            ->setItem($item)
            ->setRequestedQuantity(max(1, $item->getQuantity()))
            ->setFromSite($item->getSite())
            ->setFromLocation($item->getLocation())
            ->setReason('Demande d’inventaire')
            ->setNotes($notes)
            ->setCreatedBy($actor);

        $this->entityManager->persist($request);
        $this->entityManager->flush();

        return $request;
    }

    public function validate(InventoryRequest $request, User $actor, ?int $countedQuantity = null, ?string $resolutionNote = null): void
    {
        $item = $request->getItem();
        if (!$item instanceof InventoryItem || !$this->access->canEditItem($actor, $item)) {
            throw new AccessDeniedException();
        }
        $this->assertPending($request);

        $this->entityManager->getConnection()->beginTransaction();
        try {
            if ($request->isTransfer()) {
                $this->validateTransfer($request, $item, $actor);
            } elseif ($request->isInventory()) {
                $this->validateInventory($request, $item, $actor, $countedQuantity);
            } else {
                throw new \DomainException('Type de demande invalide.');
            }

            $request
                ->markValidated($actor)
                ->setResolutionNote($resolutionNote);

            $this->entityManager->flush();
            $this->entityManager->getConnection()->commit();
        } catch (\Throwable $throwable) {
            $this->entityManager->getConnection()->rollBack();
            throw $throwable;
        }
    }

    public function cancel(InventoryRequest $request, User $actor, ?string $reason = null): void
    {
        $item = $request->getItem();
        if (!$item instanceof InventoryItem || !$this->access->canViewItem($actor, $item)) {
            throw new AccessDeniedException();
        }
        $this->assertPending($request);

        $request
            ->markCanceled($actor)
            ->setResolutionNote($reason);

        $this->entityManager->flush();
    }

    private function validateTransfer(InventoryRequest $request, InventoryItem $item, User $actor): void
    {
        $quantity = $request->getRequestedQuantity();
        $this->assertTransferQuantity($item, $quantity);

        if ($quantity >= $item->getQuantity()) {
            $movement = $this->movementService->create(
                $this->transferMovement($request, $item, $quantity),
                $actor,
            );
            $item->setLogisticsStatus($request->getRequestedLogisticsStatus() ?? $item->getLogisticsStatus());
            $request->setMovement($movement);

            return;
        }

        $newItem = $this->cloneItemForPartialTransfer($item, $request, $quantity, $actor);
        $this->entityManager->persist($newItem);

        $this->movementService->create(
            (new InventoryMovement())
                ->setItem($item)
                ->setMovementType('adjustment')
                ->setQuantity($item->getQuantity() - $quantity)
                ->setReason(sprintf('Separation pour transport partiel demande #%d', $request->getId()))
                ->setNotes(sprintf('%d %s restent sur le site d origine.', $item->getQuantity() - $quantity, $item->getUnit())),
            $actor,
        );

        $movement = $this->movementService->create(
            $this->transferMovement($request, $newItem, $quantity),
            $actor,
        );

        $newItem->setLogisticsStatus($request->getRequestedLogisticsStatus() ?? $newItem->getLogisticsStatus());
        $request
            ->setMovement($movement)
            ->setResultItem($newItem);
    }

    private function validateInventory(InventoryRequest $request, InventoryItem $item, User $actor, ?int $countedQuantity): void
    {
        $countedQuantity ??= $request->getCountedQuantity() ?? $item->getQuantity();
        if ($countedQuantity < 0) {
            throw new \DomainException('La quantité constatée ne peut pas être négative.');
        }

        $request->setCountedQuantity($countedQuantity);
        if ($countedQuantity === $item->getQuantity()) {
            return;
        }

        $movement = $this->movementService->create(
            (new InventoryMovement())
                ->setItem($item)
                ->setMovementType('adjustment')
                ->setQuantity($countedQuantity)
                ->setReason(sprintf('Validation inventaire demande #%d', $request->getId()))
                ->setNotes($request->getNotes()),
            $actor,
        );

        $request->setMovement($movement);
    }

    private function transferMovement(InventoryRequest $request, InventoryItem $item, int $quantity): InventoryMovement
    {
        return (new InventoryMovement())
            ->setItem($item)
            ->setMovementType('transfer')
            ->setQuantity($quantity)
            ->setFromSite($request->getFromSite())
            ->setFromLocation($request->getFromLocation())
            ->setToSite($request->getToLocation()?->getSite() ?? $request->getToSite())
            ->setToLocation($request->getToLocation())
            ->setReason(sprintf('Validation transport demande #%d', $request->getId()))
            ->setNotes($request->getNotes());
    }

    private function cloneItemForPartialTransfer(InventoryItem $item, InventoryRequest $request, int $quantity, User $actor): InventoryItem
    {
        $newItem = (new InventoryItem())
            ->setReference($this->itemService->nextReference())
            ->setName((string) $item->getName())
            ->setCategory($item->getCategory())
            ->setDescription($item->getDescription())
            ->setDimensions($item->getDimensions())
            ->setColor($item->getColor())
            ->setOwnershipType($item->getOwnershipType())
            ->setOwnerName($item->getOwnerName())
            ->setQuantity($quantity)
            ->setAvailableQuantity($quantity)
            ->setUnit($item->getUnit())
            ->setSerialNumber($item->getSerialNumber())
            ->setBrand($item->getBrand())
            ->setModel($item->getModel())
            ->setCondition($item->getCondition())
            ->setStatus($item->getStatus())
            ->setLogisticsStatus($item->getLogisticsStatus())
            ->setAcquisitionDate($item->getAcquisitionDate())
            ->setEntryDate($item->getEntryDate())
            ->setAcquisitionValue($item->getAcquisitionValue())
            ->setResponsibleUser($item->getResponsibleUser())
            ->setNotes(trim(sprintf(
                "%s\n\nCréé automatiquement depuis %s pour transport partiel demande #%d.",
                $item->getNotes() ?? '',
                $item->getReference(),
                $request->getId(),
            )))
            ->setCreatedBy($actor);

        if ($request->getFromLocation() instanceof InventoryLocation) {
            $newItem->setLocation($request->getFromLocation());
        } else {
            $newItem->setSite($request->getFromSite());
        }

        return $newItem;
    }

    private function assertPending(InventoryRequest $request): void
    {
        if (!$request->isPending()) {
            throw new \DomainException('Cette demande est déjà traitée.');
        }
    }

    private function assertTransferQuantity(InventoryItem $item, int $quantity): void
    {
        if ($quantity < 1) {
            throw new \DomainException('La quantité à transporter doit être supérieure à zéro.');
        }
        if ($quantity > $item->getQuantity()) {
            throw new \DomainException('La quantité demandée dépasse la quantité totale du matériel.');
        }
        if ($quantity > $item->getAvailableQuantity()) {
            throw new \DomainException('La quantité disponible est insuffisante pour cette demande de transport.');
        }
    }
}
