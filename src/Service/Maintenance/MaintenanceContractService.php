<?php

namespace App\Service\Maintenance;

use App\Entity\MaintenanceContract;
use App\Entity\User;
use App\Repository\MaintenanceContractRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final readonly class MaintenanceContractService
{
    public function __construct(
        private MaintenanceContractRepository $repository,
        private EntityManagerInterface $entityManager,
        private MaintenanceAccessService $access,
    ) {
    }

    /** @return list<MaintenanceContract> */
    public function search(string $query, ?string $status, User $actor): array
    {
        $this->assertAccess($actor);

        return $this->repository->search($query, $status);
    }

    /** @return list<MaintenanceContract> */
    public function activeContracts(User $actor): array
    {
        $this->assertAccess($actor);

        return $this->repository->findActiveContracts();
    }

    /** @return list<string> */
    public function contractTypeSuggestions(User $actor): array
    {
        $this->assertAccess($actor);

        return $this->repository->findDistinctContractTypes();
    }

    public function nextReference(): string
    {
        $today = new \DateTimeImmutable('today');
        $prefix = 'MAINT-'.$today->format('Ymd');
        $count = (int) $this->repository->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->andWhere('c.reference LIKE :prefix')
            ->setParameter('prefix', $prefix.'-%')
            ->getQuery()
            ->getSingleScalarResult();

        return sprintf('%s-%03d', $prefix, $count + 1);
    }

    public function create(MaintenanceContract $contract, User $actor): MaintenanceContract
    {
        if (!$this->access->canCreate($actor)) {
            throw new AccessDeniedException();
        }

        $this->syncIntervenantDetails($contract);
        $this->prepareContract($contract);
        $contract->setCreatedBy($actor);
        $this->entityManager->persist($contract);
        $this->entityManager->flush();

        return $contract;
    }

    public function update(MaintenanceContract $contract, User $actor): MaintenanceContract
    {
        if (!$this->access->canEdit($actor)) {
            throw new AccessDeniedException();
        }

        $this->syncIntervenantDetails($contract);
        $this->prepareContract($contract);
        $this->entityManager->flush();

        return $contract;
    }

    public function archive(MaintenanceContract $contract, User $actor): bool
    {
        if (!$this->access->canArchive($actor)) {
            throw new AccessDeniedException();
        }

        $contract->setIsActive(!$contract->isActive());
        $this->entityManager->flush();

        return $contract->isActive();
    }

    public function delete(MaintenanceContract $contract, User $actor): void
    {
        if (!$this->access->canDelete($actor)) {
            throw new AccessDeniedException();
        }

        $this->entityManager->remove($contract);
        $this->entityManager->flush();
    }

    private function assertAccess(User $actor): void
    {
        if (!$this->access->canAccess($actor)) {
            throw new AccessDeniedException();
        }
    }

    private function syncIntervenantDetails(MaintenanceContract $contract): void
    {
        $intervenant = $contract->getIntervenant();
        if ($intervenant === null) {
            return;
        }

        $contract
            ->setCustomerName($intervenant->getDisplayName())
            ->setCustomerEmail($intervenant->getEmail())
            ->setCustomerPhone($intervenant->getPhone());
    }

    private function prepareContract(MaintenanceContract $contract): void
    {
        if (!$contract->getReference()) {
            $contract->setReference($this->nextReference());
        }

        $startDate = $contract->getStartDate();
        $endDate = $contract->getEndDate();
        if ($startDate instanceof \DateTimeImmutable && $endDate instanceof \DateTimeImmutable) {
            if ($startDate > $endDate) {
                throw new \DomainException('La date de début doit être avant la date de fin.');
            }

            if ($endDate < $startDate->modify('+1 month')) {
                throw new \DomainException('Un contrat doit durer au moins un mois.');
            }

            $contract->setRenewalDate($endDate);
        } else {
            $contract->setRenewalDate($endDate);
        }
    }
}
