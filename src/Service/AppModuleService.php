<?php

namespace App\Service;

use App\Entity\AppModule;
use App\Entity\User;
use App\Repository\AppModuleRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final readonly class AppModuleService
{
    public function __construct(
        private AppModuleRepository $repository,
        private EntityManagerInterface $entityManager,
        private SecurityAccessService $access,
    ) {
    }

    /** @return list<AppModule> */
    public function getAll(User $actor): array
    {
        $this->assertCanManage($actor);

        return $this->repository->findBy([], ['name' => 'ASC']);
    }

    public function save(AppModule $module, User $actor): AppModule
    {
        $this->assertCanManage($actor);
        $this->entityManager->persist($module);
        $this->entityManager->flush();

        return $module;
    }

    public function delete(AppModule $module, User $actor): void
    {
        $this->assertCanManage($actor);
        $this->entityManager->remove($module);
        $this->entityManager->flush();
    }

    private function assertCanManage(User $actor): void
    {
        if (!$this->access->isSuperAdmin($actor)) {
            throw new AccessDeniedException();
        }
    }
}
