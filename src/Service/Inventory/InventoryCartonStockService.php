<?php

namespace App\Service\Inventory;

use App\Entity\InventoryCartonStock;
use App\Entity\InventoryCartonStockLine;
use App\Entity\User;
use App\Repository\InventoryCartonStockLineRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final readonly class InventoryCartonStockService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private InventoryCartonStockLineRepository $lineRepository,
        private InventoryAccessService $access,
    ) {
    }

    public function createStock(InventoryCartonStock $stock, User $actor): InventoryCartonStock
    {
        $this->assertManage($actor);
        $this->entityManager->persist($stock);
        $this->entityManager->flush();

        return $stock;
    }

    public function updateStock(InventoryCartonStock $stock, User $actor): InventoryCartonStock
    {
        $this->assertManage($actor);
        $this->entityManager->flush();

        return $stock;
    }

    public function updateStockField(InventoryCartonStock $stock, string $field, mixed $value, User $actor): InventoryCartonStock
    {
        $this->assertManage($actor);

        match ($field) {
            'name' => $stock->setName((string) $value),
            'sourceSheet' => $stock->setSourceSheet($this->nullableString($value)),
            'description' => $stock->setDescription($this->nullableString($value)),
            'isActive' => $stock->setIsActive((bool) $value),
            default => throw new \DomainException('Champ stock carton invalide.'),
        };

        if (trim((string) $stock->getName()) === '') {
            throw new \DomainException('Le nom du stock carton est obligatoire.');
        }

        $this->entityManager->flush();

        return $stock;
    }

    public function deleteStock(InventoryCartonStock $stock, User $actor): void
    {
        $this->assertManage($actor);
        $this->entityManager->remove($stock);
        $this->entityManager->flush();
    }

    public function createLine(InventoryCartonStockLine $line, User $actor): InventoryCartonStockLine
    {
        $this->assertManage($actor);
        $this->prepareLine($line);
        if ($line->getStock() instanceof InventoryCartonStock && $line->getPosition() === 0) {
            $line->setPosition($this->lineRepository->nextPosition($line->getStock()));
        }

        $this->entityManager->persist($line);
        $this->entityManager->flush();

        return $line;
    }

    public function updateLine(InventoryCartonStockLine $line, User $actor): InventoryCartonStockLine
    {
        $this->assertManage($actor);
        $this->prepareLine($line);
        $this->entityManager->flush();

        return $line;
    }

    public function updateLineField(InventoryCartonStockLine $line, string $field, mixed $value, User $actor): InventoryCartonStockLine
    {
        $this->assertManage($actor);

        match ($field) {
            'groupName' => $line->setGroupName($this->nullableString($value)),
            'reference' => $line->setReference((string) $value),
            'quantity' => $line->setQuantity($this->nullableInt($value)),
            'unitPrice' => $line->setUnitPrice($this->nullableString($value)),
            'totalAmount' => $line->setTotalAmount($this->nullableString($value)),
            'lineType' => $line->setLineType($this->validLineType((string) $value)),
            'notes' => $line->setNotes($this->nullableString($value)),
            default => throw new \DomainException('Champ ligne carton invalide.'),
        };

        if (in_array($field, ['quantity', 'unitPrice'], true)
            && $line->getLineType() === 'item'
            && $line->getQuantity() !== null
            && $line->getUnitPrice() !== null) {
            $line->setTotalAmount($line->getQuantity() * (float) $line->getUnitPrice());
        }

        $this->prepareLine($line);
        $this->entityManager->flush();

        return $line;
    }

    public function deleteLine(InventoryCartonStockLine $line, User $actor): void
    {
        $this->assertManage($actor);
        $this->entityManager->remove($line);
        $this->entityManager->flush();
    }

    private function prepareLine(InventoryCartonStockLine $line): void
    {
        if (!$line->getStock() instanceof InventoryCartonStock) {
            throw new \DomainException('Sélectionnez un stock carton.');
        }

        if (trim((string) $line->getReference()) === '') {
            throw new \DomainException('La référence est obligatoire.');
        }

        if ($line->getLineType() === 'item' && $line->getTotalAmount() === null && $line->getQuantity() !== null && $line->getUnitPrice() !== null) {
            $line->setTotalAmount($line->getQuantity() * (float) $line->getUnitPrice());
        }
    }

    private function assertManage(User $actor): void
    {
        if (!$this->access->canCreate($actor)) {
            throw new AccessDeniedException();
        }
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }

    private function nullableInt(mixed $value): ?int
    {
        $value = str_replace(["\u{00A0}", ' '], '', trim((string) $value));

        return $value !== '' ? (int) $value : null;
    }

    private function validLineType(string $lineType): string
    {
        if (!in_array($lineType, InventoryCartonStockLine::LINE_TYPES, true)) {
            throw new \DomainException('Type de ligne carton invalide.');
        }

        return $lineType;
    }
}
