<?php

namespace App\Service\Inventory;

use App\Entity\ConsumableStockItem;
use App\Entity\ConsumableStockMovement;
use App\Entity\User;
use App\Repository\ConsumableStockItemRepository;
use App\Repository\ConsumableStockMovementRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final readonly class ConsumableStockService
{
    public function __construct(
        private ConsumableStockItemRepository $itemRepository,
        private ConsumableStockMovementRepository $movementRepository,
        private InventoryAccessService $access,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /** @param array<string, mixed> $filters @return list<ConsumableStockItem> */
    public function search(User $actor, array $filters = []): array
    {
        $this->denyUnlessAccess($actor);

        return $this->itemRepository->search($this->normalizeFilters($filters));
    }

    /** @return array<string, mixed> */
    public function dashboard(User $actor): array
    {
        $this->denyUnlessAccess($actor);

        $low = $this->itemRepository->countLowStock();
        $out = $this->itemRepository->countOutOfStock();

        return [
            'active' => $this->itemRepository->countActive(),
            'low' => $low,
            'out' => $out,
            'alerts' => $low + $out,
            'low_items' => $this->itemRepository->lowStockItems(8),
            'recent_movements' => $this->movementRepository->recent(10),
            'categories' => $this->itemRepository->groupByCategory(),
        ];
    }

    /** @return array{categories: list<string>, statuses: array<string, string>} */
    public function filterChoices(User $actor): array
    {
        $this->denyUnlessAccess($actor);

        return [
            'categories' => $this->itemRepository->distinctCategories(),
            'statuses' => ConsumableStockItem::LEVEL_LABELS + ['alert' => 'A commander'],
        ];
    }

    /** @return array{categories: list<string>, units: list<string>, storageLocations: list<string>, preferredSuppliers: list<string>, entrySuppliers: list<string>, recipients: list<string>} */
    public function formChoiceLists(User $actor): array
    {
        $this->denyUnlessAccess($actor);
        $suppliers = $this->mergeChoiceValues(
            $this->itemRepository->distinctValues('preferredSupplier'),
            $this->movementRepository->distinctValues('supplier'),
        );

        return [
            'categories' => $this->itemRepository->distinctValues('category'),
            'units' => $this->itemRepository->distinctValues('unit'),
            'storageLocations' => $this->itemRepository->distinctValues('storageLocation'),
            'preferredSuppliers' => $suppliers,
            'entrySuppliers' => $suppliers,
            'recipients' => $this->movementRepository->distinctValues('recipient'),
        ];
    }

    public function create(ConsumableStockItem $item, float $initialQuantity, User $actor): ConsumableStockItem
    {
        $this->denyUnlessAccess($actor);

        if (!$item->getReference()) {
            $item->setReference($this->nextReference());
        }

        $item
            ->setQuantity(0)
            ->setCreatedBy($actor);

        $this->entityManager->persist($item);
        if ($initialQuantity > 0) {
            $this->applyMovement(
                $item,
                ConsumableStockMovement::TYPE_ENTRY,
                $initialQuantity,
                new \DateTimeImmutable(),
                'Stock initial',
                null,
                null,
                null,
                $actor,
            );
        }

        $this->entityManager->flush();

        return $item;
    }

    public function update(ConsumableStockItem $item, User $actor): ConsumableStockItem
    {
        $this->denyUnlessAccess($actor);
        if (!$item->getReference()) {
            $item->setReference($this->nextReference());
        }

        $this->entityManager->flush();

        return $item;
    }

    public function receive(ConsumableStockItem $item, float $quantity, \DateTimeImmutable $date, ?string $supplier, null|float|string $unitCost, ?string $reason, User $actor): void
    {
        $this->denyUnlessAccess($actor);
        $this->assertPositive($quantity);

        $this->applyMovement($item, ConsumableStockMovement::TYPE_ENTRY, $quantity, $date, $reason, $supplier, null, $unitCost, $actor);
        $this->entityManager->flush();
    }

    public function consume(ConsumableStockItem $item, float $quantity, \DateTimeImmutable $date, ?string $recipient, ?string $reason, User $actor): void
    {
        $this->denyUnlessAccess($actor);
        $this->assertPositive($quantity);

        if ($quantity > $item->getQuantityValue()) {
            throw new \DomainException('Stock insuffisant pour cette sortie.');
        }

        $this->applyMovement($item, ConsumableStockMovement::TYPE_EXIT, -$quantity, $date, $reason, null, $recipient, null, $actor);
        $this->entityManager->flush();
    }

    public function countInventory(ConsumableStockItem $item, float $countedQuantity, \DateTimeImmutable $date, ?string $reason, User $actor): void
    {
        $this->denyUnlessAccess($actor);
        if ($countedQuantity < 0) {
            throw new \DomainException('La quantité comptée ne peut pas être négative.');
        }

        $difference = $countedQuantity - $item->getQuantityValue();
        $item->setLastInventoryAt($date);
        $this->applyMovement($item, ConsumableStockMovement::TYPE_INVENTORY, $difference, $date, $reason ?: 'Inventaire physique', null, null, null, $actor, $countedQuantity);
        $this->entityManager->flush();
    }

    /** @return list<ConsumableStockMovement> */
    public function movementsForItem(ConsumableStockItem $item, User $actor, int $limit = 20): array
    {
        $this->denyUnlessAccess($actor);

        return $this->movementRepository->forItem($item, $limit);
    }

    public function deleteItem(ConsumableStockItem $item, User $actor): void
    {
        $this->denyUnlessDelete($actor);

        $this->entityManager->remove($item);
        $this->entityManager->flush();
    }

    public function deleteMovement(ConsumableStockMovement $movement, User $actor): ConsumableStockItem
    {
        $this->denyUnlessDelete($actor);
        $item = $movement->getItem();
        if (!$item instanceof ConsumableStockItem) {
            throw new \DomainException('Mouvement de stock introuvable.');
        }

        $item->removeMovement($movement);
        $this->entityManager->remove($movement);
        $this->rebuildItemStockAfterMovementDeletion($item, $movement);
        $this->entityManager->flush();

        return $item;
    }

    /** @param array<string, mixed> $filters */
    private function normalizeFilters(array $filters): array
    {
        $status = trim((string) ($filters['status'] ?? ''));
        if ($status !== '' && !isset(ConsumableStockItem::LEVEL_LABELS[$status]) && $status !== 'alert') {
            $status = '';
        }

        return [
            'q' => trim((string) ($filters['q'] ?? '')),
            'category' => trim((string) ($filters['category'] ?? '')),
            'status' => $status,
            'active' => in_array((string) ($filters['active'] ?? 'active'), ['active', 'archived', 'all'], true) ? (string) ($filters['active'] ?? 'active') : 'active',
        ];
    }

    private function applyMovement(ConsumableStockItem $item, string $type, float $quantity, \DateTimeImmutable $date, ?string $reason, ?string $supplier, ?string $recipient, null|float|string $unitCost, User $actor, ?float $forcedNewQuantity = null): void
    {
        $previous = $item->getQuantityValue();
        $new = $forcedNewQuantity ?? max(0.0, $previous + $quantity);
        $item->setQuantity($new);

        $movement = (new ConsumableStockMovement())
            ->setItem($item)
            ->setMovementType($type)
            ->setQuantity($quantity)
            ->setPreviousQuantity($previous)
            ->setNewQuantity($new)
            ->setMovementDate($date)
            ->setReason($reason)
            ->setSupplier($supplier)
            ->setRecipient($recipient)
            ->setUnitCost($unitCost)
            ->setPerformedBy($actor)
            ->setCreatedBy($actor);

        $item->addMovement($movement);
        $this->entityManager->persist($movement);
    }

    private function rebuildItemStockAfterMovementDeletion(ConsumableStockItem $item, ConsumableStockMovement $deletedMovement): void
    {
        $currentQuantity = 0.0;
        $lastInventoryAt = null;

        foreach ($this->movementRepository->chronologicalForItem($item) as $movement) {
            if ($movement === $deletedMovement || $movement->getId() === $deletedMovement->getId()) {
                continue;
            }

            $previousQuantity = $currentQuantity;
            if ($movement->getMovementType() === ConsumableStockMovement::TYPE_INVENTORY) {
                $currentQuantity = max(0.0, (float) $movement->getNewQuantity());
                $movement->setQuantity($currentQuantity - $previousQuantity);
                $lastInventoryAt = $movement->getMovementDate();
            } else {
                $currentQuantity = max(0.0, $previousQuantity + $movement->getQuantityValue());
            }

            $movement
                ->setPreviousQuantity($previousQuantity)
                ->setNewQuantity($currentQuantity);
        }

        $item
            ->setQuantity($currentQuantity)
            ->setLastInventoryAt($lastInventoryAt);
    }

    private function assertPositive(float $quantity): void
    {
        if ($quantity <= 0) {
            throw new \DomainException('La quantité doit être supérieure à zéro.');
        }
    }

    /** @param list<string> ...$groups @return list<string> */
    private function mergeChoiceValues(array ...$groups): array
    {
        $values = [];
        foreach ($groups as $group) {
            foreach ($group as $value) {
                $value = trim((string) $value);
                if ($value === '') {
                    continue;
                }

                $values[mb_strtolower($value)] ??= $value;
            }
        }

        natcasesort($values);

        return array_values($values);
    }

    private function denyUnlessAccess(User $actor): void
    {
        if (!$this->access->canAccess($actor)) {
            throw new AccessDeniedException();
        }
    }

    private function denyUnlessDelete(User $actor): void
    {
        $this->denyUnlessAccess($actor);
        if (!$this->access->isAdmin($actor)) {
            throw new \DomainException('Suppression reservee aux administrateurs.');
        }
    }

    private function nextReference(): string
    {
        $next = $this->itemRepository->count([]) + 1;
        do {
            $reference = sprintf('STK-%04d', $next++);
        } while ($this->itemRepository->findOneBy(['reference' => $reference]) instanceof ConsumableStockItem);

        return $reference;
    }
}
