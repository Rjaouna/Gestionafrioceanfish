<?php

namespace App\Service\Maintenance;

use App\Entity\Intervenant;
use App\Entity\User;
use App\Repository\IntervenantRepository;
use App\Service\Trash\TrashService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final readonly class IntervenantService
{
    public function __construct(
        private IntervenantRepository $repository,
        private EntityManagerInterface $entityManager,
        private MaintenanceAccessService $access,
        private TrashService $trashService,
        private MaintenanceShareService $shareService,
    ) {
    }

    /** @return list<Intervenant> */
    public function search(string $query, User $actor): array
    {
        $this->assertAccess($actor);

        return $this->shareService->filterVisible($actor, MaintenanceShareService::TYPE_INTERVENANT, $this->repository->search($query));
    }

    /** @return list<Intervenant> */
    public function available(User $actor): array
    {
        $this->assertAccess($actor);

        return $this->shareService->filterVisible($actor, MaintenanceShareService::TYPE_INTERVENANT, $this->repository->findActive());
    }

    public function create(Intervenant $intervenant, User $actor): Intervenant
    {
        $this->assertCanEdit($actor);
        $this->assertNoDuplicate($intervenant);
        $intervenant->setCreatedBy($actor);
        $this->entityManager->persist($intervenant);
        $this->entityManager->flush();
        if ($intervenant->getId() !== null) {
            $this->shareService->ensureCreatorShare(MaintenanceShareService::TYPE_INTERVENANT, $intervenant->getId(), $actor);
        }

        return $intervenant;
    }

    public function update(Intervenant $intervenant, User $actor): Intervenant
    {
        $this->assertCanEdit($actor);
        if ($intervenant->isDeleted() || !$this->shareService->canViewObject($actor, $intervenant)) {
            throw new AccessDeniedException();
        }

        $this->assertNoDuplicate($intervenant);
        $this->entityManager->flush();

        return $intervenant;
    }

    public function toggle(Intervenant $intervenant, User $actor): bool
    {
        $this->assertCanEdit($actor);
        if ($intervenant->isDeleted() || !$this->shareService->canViewObject($actor, $intervenant)) {
            throw new AccessDeniedException();
        }

        $intervenant->setIsActive(!$intervenant->isActive());
        $this->entityManager->flush();

        return $intervenant->isActive();
    }

    public function delete(Intervenant $intervenant, User $actor): bool
    {
        if ($intervenant->isDeleted()) {
            throw new AccessDeniedException();
        }

        if (!$this->access->canDelete($actor)) {
            throw new AccessDeniedException();
        }

        if (!$this->access->isSuperAdmin($actor)) {
            $this->trashService->moveToTrash($intervenant, $actor);

            return true;
        }

        $this->shareService->removeSharesFor($intervenant);
        $this->entityManager->remove($intervenant);
        $this->entityManager->flush();

        return false;
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

    private function assertNoDuplicate(Intervenant $intervenant): void
    {
        $email = $this->normalizeText($intervenant->getEmail());
        $companyName = $this->normalizeText($intervenant->getCompanyName());
        $phone = $this->normalizePhone($intervenant->getPhone());

        if ($email === '' && $companyName === '' && $phone === '') {
            return;
        }

        foreach ($this->repository->findDuplicateCandidates($intervenant->getId()) as $candidate) {
            if ($email !== '' && $email === $this->normalizeText($candidate->getEmail())) {
                throw new \DomainException('Un intervenant existe déjà avec cet e-mail.');
            }

            if ($phone !== '' && $phone === $this->normalizePhone($candidate->getPhone())) {
                throw new \DomainException('Un intervenant existe déjà avec ce numéro de téléphone.');
            }

            if ($companyName !== '' && $companyName === $this->normalizeText($candidate->getCompanyName())) {
                throw new \DomainException('Un intervenant existe déjà avec ce nom de boîte.');
            }
        }
    }

    private function normalizeText(?string $value): string
    {
        return mb_strtolower(trim((string) $value));
    }

    private function normalizePhone(?string $value): string
    {
        return preg_replace('/[^\d+]/', '', (string) $value) ?? '';
    }
}
