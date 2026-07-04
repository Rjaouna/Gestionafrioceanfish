<?php

namespace App\Service\CoutRevient;

use App\Entity\CoutRevientChargeConfig;
use App\Entity\User;
use App\Repository\CoutRevientChargeConfigRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final readonly class CoutRevientChargeConfigService
{
    public function __construct(
        private CoutRevientChargeConfigRepository $repository,
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

    private function assertAccess(User $actor): void
    {
        if (!$this->permission->canAccess($actor)) {
            throw new AccessDeniedException();
        }
    }
}
