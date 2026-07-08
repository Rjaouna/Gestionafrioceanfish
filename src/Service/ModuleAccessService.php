<?php

namespace App\Service;

use App\Entity\AppModule;
use App\Entity\User;
use App\Entity\UserModuleAccess;
use App\Repository\AppModuleRepository;
use App\Repository\UserModuleAccessRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final readonly class ModuleAccessService
{
    public function __construct(
        private AppModuleRepository $moduleRepository,
        private UserModuleAccessRepository $accessRepository,
        private EntityManagerInterface $entityManager,
        private SecurityAccessService $securityAccess,
    ) {
    }

    /** @return list<AppModule> */
    public function getAccessibleModules(User $user): array
    {
        if ($this->securityAccess->isSuperAdmin($user)) {
            return $this->moduleRepository->findBy(['isActive' => true], ['name' => 'ASC']);
        }

        $modules = $this->moduleRepository->findActiveForUser($user);
        if ($this->securityAccess->isAdmin($user)) {
            foreach (['passwords', 'cout-revient', 'pointage-personnel', 'etudes-rendement-poisson', 'ventes-dechets'] as $adminModuleSlug) {
                $adminModule = $this->moduleRepository->findOneBy(['slug' => $adminModuleSlug, 'isActive' => true]);
                if ($adminModule && !in_array($adminModule, $modules, true)) {
                    $modules[] = $adminModule;
                }
            }
            usort($modules, static fn (AppModule $a, AppModule $b): int => strcmp((string) $a->getName(), (string) $b->getName()));
        }

        return $modules;
    }

    public function canAccess(User $user, string $slug): bool
    {
        return $this->securityAccess->canAccessModule($user, $slug);
    }

    /** @param list<int|string> $moduleIds */
    public function synchronizeUserModules(User $user, array $moduleIds, User $actor, bool $flush = true): void
    {
        if (!$this->securityAccess->canManageUsers($actor)) {
            throw new AccessDeniedException();
        }

        $requested = array_fill_keys(array_map('intval', $moduleIds), true);
        foreach ($user->getModuleAccesses()->toArray() as $access) {
            $moduleId = $access->getModule()?->getId();
            if ($moduleId !== null && !isset($requested[$moduleId])) {
                $this->entityManager->remove($access);
            } else {
                unset($requested[$moduleId]);
            }
        }

        foreach (array_keys($requested) as $moduleId) {
            $module = $this->moduleRepository->find($moduleId);
            if (!$module instanceof AppModule || !$module->isActive()) {
                continue;
            }

            $access = (new UserModuleAccess())->setUser($user)->setModule($module);
            $this->entityManager->persist($access);
        }

        if ($flush) {
            $this->entityManager->flush();
        }
    }

    public function removeAccess(UserModuleAccess $access, User $actor): void
    {
        if (!$this->securityAccess->canManageUsers($actor)) {
            throw new AccessDeniedException();
        }

        $this->entityManager->remove($access);
        $this->entityManager->flush();
    }
}
