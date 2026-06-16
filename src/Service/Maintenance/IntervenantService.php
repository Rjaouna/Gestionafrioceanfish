<?php

namespace App\Service\Maintenance;

use App\Entity\Intervenant;
use App\Entity\User;
use App\Repository\IntervenantRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final readonly class IntervenantService
{
    public function __construct(
        private IntervenantRepository $repository,
        private EntityManagerInterface $entityManager,
        private MaintenanceAccessService $access,
    ) {
    }

    /** @return list<Intervenant> */
    public function search(string $query, User $actor): array
    {
        $this->assertAccess($actor);

        return $this->repository->search($query);
    }

    /** @return list<Intervenant> */
    public function available(User $actor): array
    {
        $this->assertAccess($actor);

        return $this->repository->findActive();
    }

    public function create(Intervenant $intervenant, User $actor): Intervenant
    {
        $this->assertCanEdit($actor);
        $intervenant->setCreatedBy($actor);
        $this->entityManager->persist($intervenant);
        $this->entityManager->flush();

        return $intervenant;
    }

    public function update(Intervenant $intervenant, User $actor): Intervenant
    {
        $this->assertCanEdit($actor);
        $this->entityManager->flush();

        return $intervenant;
    }

    public function toggle(Intervenant $intervenant, User $actor): bool
    {
        $this->assertCanEdit($actor);
        $intervenant->setIsActive(!$intervenant->isActive());
        $this->entityManager->flush();

        return $intervenant->isActive();
    }

    public function delete(Intervenant $intervenant, User $actor): void
    {
        if (!$this->access->canDelete($actor)) {
            throw new AccessDeniedException();
        }

        $this->entityManager->remove($intervenant);
        $this->entityManager->flush();
    }

    private function assertAccess(User $actor): void
    {
        if (!$this->access->canAccess($actor)) {
            throw new AccessDeniedException();
        }
    }

    private function assertCanEdit(User $actor): void
    {
        if (!$this->access->canEdit($actor)) {
            throw new AccessDeniedException();
        }
    }
}
