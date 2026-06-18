<?php

namespace App\Service\Inventory;

use App\Entity\InventoryCampaign;
use App\Entity\InventoryCampaignLine;
use App\Entity\InventoryMovement;
use App\Entity\User;
use App\Repository\InventoryCampaignRepository;
use App\Repository\InventoryItemRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final readonly class InventoryCampaignService
{
    public function __construct(
        private InventoryCampaignRepository $repository,
        private InventoryItemRepository $itemRepository,
        private EntityManagerInterface $entityManager,
        private InventoryAccessService $access,
        private InventoryMovementService $movementService,
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

    public function nextReference(): string
    {
        $today = new \DateTimeImmutable('today');
        $prefix = 'PHY-'.$today->format('Ymd');

        return sprintf('%s-%03d', $prefix, $this->repository->nextReferenceNumber($prefix));
    }

    public function create(InventoryCampaign $campaign, User $actor): InventoryCampaign
    {
        if (!$this->access->canManageCampaign($actor)) {
            throw new AccessDeniedException();
        }

        $this->prepare($campaign);
        $campaign->setCreatedBy($actor);
        if (!$campaign->getResponsibleUser() instanceof User) {
            $campaign->setResponsibleUser($actor);
        }

        $itemsFilters = ['active' => 'active'];
        if ($campaign->getSite()?->getId()) {
            $itemsFilters['site'] = $campaign->getSite()->getId();
        }

        $result = $this->itemRepository->searchVisible($actor, $this->access->canViewAll($actor), $itemsFilters, 1, 500);
        foreach ($result['items'] as $item) {
            $line = (new InventoryCampaignLine())
                ->setItem($item)
                ->setTheoreticalQuantity($item->getQuantity())
                ->setTheoreticalLocation($item->getLocation()?->getDisplayName() ?? $item->getSite()?->getName());
            $campaign->addLine($line);
        }

        $this->entityManager->persist($campaign);
        $this->entityManager->flush();

        return $campaign;
    }

    public function update(InventoryCampaign $campaign, User $actor): InventoryCampaign
    {
        if (!$this->access->canManageCampaign($actor, $campaign)) {
            throw new AccessDeniedException();
        }

        $this->prepare($campaign);
        $this->entityManager->flush();

        return $campaign;
    }

    public function updateLine(InventoryCampaignLine $line, User $actor): InventoryCampaignLine
    {
        $campaign = $line->getCampaign();
        if (!$campaign instanceof InventoryCampaign || !$this->access->canManageCampaign($actor, $campaign)) {
            throw new AccessDeniedException();
        }

        $line->setCheckedBy($actor)->setCheckedAt(new \DateTimeImmutable());
        if ($line->hasDiscrepancy() && $line->getCheckStatus() === 'pending') {
            $line->setCheckStatus('discrepancy');
        }
        if (!$line->hasDiscrepancy() && $line->getCountedQuantity() !== null && $line->getCheckStatus() === 'pending') {
            $line->setCheckStatus('ok');
        }

        $this->entityManager->flush();

        return $line;
    }

    public function validate(InventoryCampaign $campaign, User $actor): void
    {
        if (!$this->access->canManageCampaign($actor, $campaign)) {
            throw new AccessDeniedException();
        }

        $campaign->setStatus('validated')->setEndDate(new \DateTimeImmutable('today'));
        $this->entityManager->flush();
    }

    public function createAdjustmentFromLine(InventoryCampaignLine $line, User $actor): InventoryMovement
    {
        $campaign = $line->getCampaign();
        $item = $line->getItem();
        if (!$campaign instanceof InventoryCampaign || $item === null || !$this->access->canManageCampaign($actor, $campaign)) {
            throw new AccessDeniedException();
        }
        if ($line->getCountedQuantity() === null) {
            throw new \DomainException('Renseignez une quantité comptée avant de créer un ajustement.');
        }

        $movement = (new InventoryMovement())
            ->setItem($item)
            ->setMovementType('adjustment')
            ->setQuantity($line->getCountedQuantity())
            ->setReason('Ajustement inventaire physique '.$campaign->getReference())
            ->setNotes($line->getComment());

        return $this->movementService->create($movement, $actor);
    }

    private function prepare(InventoryCampaign $campaign): void
    {
        if (!$campaign->getReference()) {
            $campaign->setReference($this->nextReference());
        }

        if ($campaign->getEndDate() instanceof \DateTimeImmutable && $campaign->getEndDate() < $campaign->getStartDate()) {
            throw new \DomainException('La date de fin doit être après la date de début.');
        }
    }
}
