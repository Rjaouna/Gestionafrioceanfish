<?php

namespace App\Service;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final readonly class UserManagementService
{
    public function __construct(
        private UserRepository $repository,
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher,
        private ModuleAccessService $moduleAccessService,
        private SecurityAccessService $access,
    ) {
    }

    /** @return list<User> */
    public function getUsers(User $actor): array
    {
        $this->assertCanManage($actor);

        return $this->repository->findBy([], ['lastName' => 'ASC', 'firstName' => 'ASC', 'email' => 'ASC']);
    }

    /** @param list<int|string> $moduleIds */
    public function create(User $user, string $plainPassword, array $moduleIds, User $actor, ?string $role = null): User
    {
        $this->assertCanManage($actor);
        $this->applyRole($user, $role, $actor, true);
        $this->assertPassword($plainPassword);

        $user->setPassword($this->passwordHasher->hashPassword($user, $plainPassword));
        $this->entityManager->persist($user);
        $this->moduleAccessService->synchronizeUserModules($user, $moduleIds, $actor, false);
        $this->entityManager->flush();

        return $user;
    }

    /** @param list<int|string> $moduleIds */
    public function update(User $user, ?string $plainPassword, array $moduleIds, User $actor, ?string $role = null): User
    {
        $this->assertCanManage($actor);
        $this->assertCanManageTarget($user, $actor);
        $this->applyRole($user, $role, $actor, false);

        if ($plainPassword !== null && $plainPassword !== '') {
            $this->assertPassword($plainPassword);
            $user->setPassword($this->passwordHasher->hashPassword($user, $plainPassword));
        }

        $this->moduleAccessService->synchronizeUserModules($user, $moduleIds, $actor, false);
        $this->entityManager->flush();

        return $user;
    }

    public function toggleActive(User $user, User $actor): bool
    {
        $this->assertCanManage($actor);
        if ($user === $actor) {
            throw new \DomainException('Vous ne pouvez pas désactiver votre propre compte.');
        }

        if ($this->access->isSuperAdmin($user) && !$this->access->isSuperAdmin($actor)) {
            throw new AccessDeniedException();
        }

        $user->setIsActive(!$user->isActive());
        $this->entityManager->flush();

        return $user->isActive();
    }

    public function delete(User $user, User $actor): void
    {
        if (!$this->access->isSuperAdmin($actor)) {
            throw new AccessDeniedException();
        }

        if ($user === $actor) {
            throw new \DomainException('Vous ne pouvez pas supprimer votre propre compte.');
        }

        $this->entityManager->remove($user);
        $this->entityManager->flush();
    }

    private function assertCanManage(User $actor): void
    {
        if (!$this->access->canManageUsers($actor)) {
            throw new AccessDeniedException();
        }
    }

    private function assertCanManageTarget(User $user, User $actor): void
    {
        if ($this->access->isSuperAdmin($user) && !$this->access->isSuperAdmin($actor)) {
            throw new AccessDeniedException('Seul un super administrateur peut modifier ce compte.');
        }
    }

    private function applyRole(User $user, ?string $role, User $actor, bool $creating): void
    {
        if (!$this->access->isSuperAdmin($actor)) {
            if (!$creating && $this->isSameUser($user, $actor) && $role !== null && $role !== '' && $role !== $this->primaryRole($user)) {
                throw new \DomainException('Vous ne pouvez pas modifier votre propre rôle.');
            }

            if ($creating) {
                $user->setRoles(['ROLE_USER']);
            }

            return;
        }

        if (!in_array($role, ['ROLE_USER', 'ROLE_ADMIN', 'ROLE_SUPER_ADMIN'], true)) {
            throw new \DomainException('Le rôle sélectionné est invalide.');
        }

        if (!$creating && $this->isSameUser($user, $actor) && $role !== $this->primaryRole($user)) {
            throw new \DomainException('Vous devez conserver votre rôle actuel.');
        }

        $user->setRoles([$role]);
    }

    private function isSameUser(User $user, User $actor): bool
    {
        return $user === $actor
            || ($user->getId() !== null && $actor->getId() !== null && $user->getId() === $actor->getId());
    }

    private function primaryRole(User $user): string
    {
        foreach (['ROLE_SUPER_ADMIN', 'ROLE_ADMIN'] as $role) {
            if (in_array($role, $user->getRoles(), true)) {
                return $role;
            }
        }

        return 'ROLE_USER';
    }

    private function assertPassword(string $plainPassword): void
    {
        if (strlen($plainPassword) < 12) {
            throw new \DomainException('Le mot de passe utilisateur doit contenir au moins 12 caractères.');
        }
    }
}
