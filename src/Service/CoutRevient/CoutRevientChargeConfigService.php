<?php

namespace App\Service\CoutRevient;

use App\Entity\CoutRevientChargeConfig;
use App\Entity\User;
use App\Repository\CoutRevientChargeConfigRepository;
use App\Repository\CoutRevientChargeLineRepository;
use App\Repository\DailyProductionCostChargeLineRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final readonly class CoutRevientChargeConfigService
{
    public function __construct(
        private CoutRevientChargeConfigRepository $repository,
        private CoutRevientChargeLineRepository $chargeLineRepository,
        private DailyProductionCostChargeLineRepository $dailyChargeLineRepository,
        private EntityManagerInterface $entityManager,
        private CoutRevientPermissionService $permission,
    ) {
    }

    /** @return list<CoutRevientChargeConfig> */
    public function search(User $actor, string $query = ''): array
    {
        $this->assertAccess($actor);

        return $this->repository->search($query);
    }

    /** @return list<CoutRevientChargeConfig> */
    public function active(User $actor): array
    {
        $this->assertAccess($actor);

        return $this->repository->findActive();
    }

    /** @return list<CoutRevientChargeConfig> */
    public function forLotSelection(User $actor): array
    {
        $this->assertAccess($actor);

        return $this->repository->findForLotSelection();
    }

    public function create(CoutRevientChargeConfig $config, User $actor): CoutRevientChargeConfig
    {
        $this->assertAccess($actor);
        $config->setCreatedBy($actor);

        $this->entityManager->persist($config);
        $this->entityManager->flush();

        return $config;
    }

    public function update(CoutRevientChargeConfig $config, User $actor): CoutRevientChargeConfig
    {
        $this->assertAccess($actor);
        $config->setUpdatedBy($actor);

        $this->entityManager->flush();

        return $config;
    }

    public function toggle(CoutRevientChargeConfig $config, User $actor): bool
    {
        $this->assertAccess($actor);
        $config
            ->setIsActive(!$config->isActive())
            ->setUpdatedBy($actor);

        $this->entityManager->flush();

        return $config->isActive();
    }

    public function delete(CoutRevientChargeConfig $config, User $actor): int
    {
        $this->assertAccess($actor);
        $detachedLines = $this->chargeLineRepository->detachConfigReferences($config);
        $detachedLines += $this->dailyChargeLineRepository->detachConfigReferences($config);

        $this->entityManager->remove($config);
        $this->entityManager->flush();

        return $detachedLines;
    }

    private function assertAccess(User $actor): void
    {
        if (!$this->permission->canAccess($actor)) {
            throw new AccessDeniedException();
        }
    }
}
