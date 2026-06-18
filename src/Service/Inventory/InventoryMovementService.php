<?php

namespace App\Service\Inventory;

use App\Entity\InventoryItem;
use App\Entity\InventoryMovement;
use App\Entity\User;
use App\Repository\InventoryMovementRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final readonly class InventoryMovementService
{
    public function __construct(
        private InventoryMovementRepository $repository,
        private EntityManagerInterface $entityManager,
        private InventoryAccessService $access,
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

    public function create(InventoryMovement $movement, User $actor): InventoryMovement
    {
        $item = $movement->getItem();
        if (!$item instanceof InventoryItem || !$this->access->canEditItem($actor, $item)) {
            throw new AccessDeniedException();
        }

        $this->entityManager->getConnection()->beginTransaction();
        try {
            $this->prepare($movement, $item);
            $this->applyMovement($movement, $item);
            $movement->setCreatedBy($actor);
            $this->entityManager->persist($movement);
            $this->entityManager->flush();
            $this->entityManager->getConnection()->commit();
        } catch (\Throwable $throwable) {
            $this->entityManager->getConnection()->rollBack();
            throw $throwable;
        }

        return $movement;
    }

    private function prepare(InventoryMovement $movement, InventoryItem $item): void
    {
        if ($movement->getMovementType() !== 'adjustment' && $movement->getQuantity() < 1) {
            throw new \DomainException('La quantite du mouvement doit etre superieure a zero.');
        }

        if ($movement->getFromSite() === null) {
            $movement->setFromSite($item->getSite());
        }

        if ($movement->getFromLocation() === null) {
            $movement->setFromLocation($item->getLocation());
        }
    }

    private function applyMovement(InventoryMovement $movement, InventoryItem $item): void
    {
        $quantity = $movement->getQuantity();

        match ($movement->getMovementType()) {
            'entry' => $this->applyEntry($movement, $item, $quantity),
            'transfer' => $this->applyTransfer($movement, $item),
            'assignment' => $this->applyOutgoing($item, $quantity, 'assigned', $movement->getResponsibleUser()),
            'return' => $this->applyReturn($item, $quantity),
            'maintenance' => $this->applyOutgoing($item, $quantity, 'maintenance', $movement->getResponsibleUser()),
            'adjustment' => $this->applyAdjustment($item, $quantity),
            'retirement' => $this->applyRetirement($item, $quantity),
            default => throw new \DomainException('Type de mouvement invalide.'),
        };
    }

    private function applyEntry(InventoryMovement $movement, InventoryItem $item, int $quantity): void
    {
        $item
            ->setQuantity($item->getQuantity() + $quantity)
            ->setAvailableQuantity($item->getAvailableQuantity() + $quantity)
            ->setStatus('available');
        $this->applyTransfer($movement, $item);
    }

    private function applyTransfer(InventoryMovement $movement, InventoryItem $item): void
    {
        if ($movement->getToLocation() !== null) {
            $item->setLocation($movement->getToLocation());
        } elseif ($movement->getToSite() !== null) {
            $item->setSite($movement->getToSite())->setLocation(null);
        }
    }

    private function applyOutgoing(InventoryItem $item, int $quantity, string $status, ?User $responsibleUser): void
    {
        if ($item->getAvailableQuantity() < $quantity) {
            throw new \DomainException('La quantite disponible est insuffisante pour ce mouvement.');
        }

        $item->setAvailableQuantity($item->getAvailableQuantity() - $quantity)->setStatus($status);
        if ($responsibleUser instanceof User) {
            $item->setResponsibleUser($responsibleUser);
        }
    }

    private function applyReturn(InventoryItem $item, int $quantity): void
    {
        $item->setAvailableQuantity(min($item->getQuantity(), $item->getAvailableQuantity() + $quantity));
        if ($item->getAvailableQuantity() >= $item->getQuantity()) {
            $item->setStatus('available');
        }
    }

    private function applyAdjustment(InventoryItem $item, int $newQuantity): void
    {
        $oldQuantity = $item->getQuantity();
        $oldAvailable = $item->getAvailableQuantity();
        $delta = $newQuantity - $oldQuantity;
        $item
            ->setQuantity($newQuantity)
            ->setAvailableQuantity(max(0, min($newQuantity, $oldAvailable + $delta)));
    }

    private function applyRetirement(InventoryItem $item, int $quantity): void
    {
        if ($quantity > $item->getQuantity()) {
            throw new \DomainException('La sortie depasse la quantite totale.');
        }

        $item
            ->setQuantity($item->getQuantity() - $quantity)
            ->setAvailableQuantity(min($item->getAvailableQuantity(), $item->getQuantity()));

        if ($item->getQuantity() === 0) {
            $item->setStatus('retired')->setIsActive(false);
        }
    }
}
