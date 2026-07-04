<?php

namespace App\Service;

use App\Entity\PasswordEntry;
use App\Entity\User;
use App\Repository\AppModuleRepository;
use App\Repository\PasswordShareRepository;
use App\Repository\UserModuleAccessRepository;

final readonly class SecurityAccessService
{
    public function __construct(
        private PasswordShareRepository $shareRepository,
        private AppModuleRepository $moduleRepository,
        private UserModuleAccessRepository $moduleAccessRepository,
    ) {
    }

    public function isSuperAdmin(User $user): bool
    {
        return in_array('ROLE_SUPER_ADMIN', $user->getRoles(), true);
    }

    public function isAdmin(User $user): bool
    {
        return $this->isSuperAdmin($user) || in_array('ROLE_ADMIN', $user->getRoles(), true);
    }

    public function canDeletePasswords(User $user): bool
    {
        return $this->isAdmin($user);
    }

    public function canViewPassword(User $user, PasswordEntry $entry): bool
    {
        if ($entry->isDeleted()) {
            return false;
        }

        if ($this->isAdmin($user)) {
            return true;
        }

        if (!$entry->isActive()) {
            return false;
        }

        return $this->shareRepository->findFor($entry, $user)?->canView() ?? false;
    }

    public function canEditPasswordEntry(User $user): bool
    {
        return $this->isAdmin($user);
    }

    public function canSharePassword(User $user, ?PasswordEntry $entry = null): bool
    {
        if ($entry instanceof PasswordEntry && $entry->isDeleted()) {
            return false;
        }

        return $this->isAdmin($user);
    }

    public function canEditPasswordValue(User $user, PasswordEntry $entry): bool
    {
        if ($entry->isDeleted()) {
            return false;
        }

        if ($this->isAdmin($user)) {
            return true;
        }

        if (!$entry->isActive()) {
            return false;
        }

        return $this->shareRepository->findFor($entry, $user)?->canEditPassword() ?? false;
    }

    public function canValidatePassword(User $user, PasswordEntry $entry): bool
    {
        return $this->isAdmin($user) && !$entry->isDeleted() && !$entry->isValidated() && $entry->isActive();
    }

    public function canTogglePasswordStatus(User $user, PasswordEntry $entry): bool
    {
        if ($entry->isDeleted()) {
            return false;
        }

        if ($this->isAdmin($user)) {
            return true;
        }

        $share = $this->shareRepository->findFor($entry, $user);

        return $this->isCreator($user, $entry) && ($share?->canView() ?? false);
    }

    private function isCreator(User $user, PasswordEntry $entry): bool
    {
        return $user->getId() !== null
            && $entry->getCreatedBy()?->getId() === $user->getId();
    }

    public function canAccessModule(User $user, string $slug): bool
    {
        if (!$user->isActive()) {
            return false;
        }

        if ($this->isSuperAdmin($user) || ($this->isAdmin($user) && in_array($slug, ['passwords', 'cout-revient'], true))) {
            return true;
        }

        $module = $this->moduleRepository->findOneBy(['slug' => $slug, 'isActive' => true]);

        return $module !== null && $this->moduleAccessRepository->hasAccess($user, $module);
    }

    public function canManageUsers(User $user): bool
    {
        return $this->isAdmin($user) && $this->canAccessModule($user, 'users');
    }
}
