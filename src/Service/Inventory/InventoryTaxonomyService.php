<?php

namespace App\Service\Inventory;

use App\Entity\InventoryLocation;
use App\Entity\InventorySite;
use App\Entity\User;
use App\Repository\InventoryItemRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final readonly class InventoryTaxonomyService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private InventoryItemRepository $itemRepository,
        private InventoryAccessService $access,
    ) {
    }

    public function deleteSite(InventorySite $site, ?InventorySite $destinationSite, User $actor): int
    {
        $this->assertManage($actor);
        if ($destinationSite instanceof InventorySite && $destinationSite->getId() === $site->getId()) {
            throw new \DomainException('Selectionnez un autre site de destination.');
        }

        $items = $this->itemRepository->attachedToSite($site);
        $this->entityManager->getConnection()->beginTransaction();
        try {
            foreach ($items as $item) {
                $item->setLocation(null);
                $item->setSite($destinationSite);
            }

            foreach ($site->getLocations() as $location) {
                $this->entityManager->remove($location);
            }

            $this->entityManager->remove($site);
            $this->entityManager->flush();
            $this->entityManager->getConnection()->commit();
        } catch (\Throwable $throwable) {
            $this->entityManager->getConnection()->rollBack();
            throw $throwable;
        }

        return count($items);
    }

    public function deleteLocation(InventoryLocation $location, ?InventoryLocation $destinationLocation, User $actor): int
    {
        $this->assertManage($actor);
        if ($destinationLocation instanceof InventoryLocation && $destinationLocation->getId() === $location->getId()) {
            throw new \DomainException('Selectionnez un autre emplacement de destination.');
        }

        $items = $this->itemRepository->attachedToLocation($location);
        $this->entityManager->getConnection()->beginTransaction();
        try {
            foreach ($items as $item) {
                $item->setLocation($destinationLocation);
            }

            $this->entityManager->remove($location);
            $this->entityManager->flush();
            $this->entityManager->getConnection()->commit();
        } catch (\Throwable $throwable) {
            $this->entityManager->getConnection()->rollBack();
            throw $throwable;
        }

        return count($items);
    }

    private function assertManage(User $actor): void
    {
        if (!$this->access->canCreate($actor)) {
            throw new AccessDeniedException();
        }
    }
}
