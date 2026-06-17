<?php

namespace App\Service;

use App\Entity\PasswordEntry;
use App\Entity\PasswordShare;
use App\Entity\User;
use App\Repository\PasswordEntryRepository;
use App\Service\Trash\TrashService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final readonly class PasswordEntryService
{
    public function __construct(
        private PasswordEntryRepository $repository,
        private EntityManagerInterface $entityManager,
        private PasswordCipher $cipher,
        private SecurityAccessService $access,
        private TrashService $trashService,
    ) {
    }

    /** @return list<PasswordEntry> */
    public function getVisibleEntries(User $user): array
    {
        if ($this->access->isAdmin($user)) {
            return $this->repository->findAllForAdmin();
        }

        return $this->repository->findVisibleFor($user);
    }

    public function countPendingValidation(User $user): int
    {
        return $this->repository->countPendingValidation($this->access->isAdmin($user) ? null : $user);
    }

    public function create(PasswordEntry $entry, string $plainPassword, User $actor): PasswordEntry
    {
        $this->assertPassword($plainPassword);
        $entry->setIsValidated($this->access->isAdmin($actor));
        $entry->setIsActive(true);
        $entry->setCreatedBy($actor);
        $entry->setEncryptedPassword($this->cipher->encrypt($plainPassword));

        if (!$this->access->isAdmin($actor)) {
            $entry->addShare(
                (new PasswordShare())
                    ->setUser($actor)
                    ->setCanView(true)
                    ->setCanEditPassword(true),
            );
        }

        $this->entityManager->persist($entry);
        $this->entityManager->flush();

        return $entry;
    }

    public function update(PasswordEntry $entry, ?string $plainPassword, User $actor): PasswordEntry
    {
        if ($entry->isDeleted() || !$this->access->canEditPasswordEntry($actor)) {
            throw new AccessDeniedException();
        }

        if ($plainPassword !== null && $plainPassword !== '') {
            $this->assertPassword($plainPassword);
            $entry->setEncryptedPassword($this->cipher->encrypt($plainPassword));
        }

        $this->entityManager->flush();

        return $entry;
    }

    public function updatePasswordValue(PasswordEntry $entry, string $plainPassword, User $actor): void
    {
        if (!$this->access->canEditPasswordValue($actor, $entry)) {
            throw new AccessDeniedException();
        }

        $this->assertPassword($plainPassword);
        $entry->setEncryptedPassword($this->cipher->encrypt($plainPassword));
        $this->entityManager->flush();
    }

    public function reveal(PasswordEntry $entry, User $actor): string
    {
        if (!$this->access->canViewPassword($actor, $entry)) {
            throw new AccessDeniedException();
        }

        return $this->cipher->decrypt((string) $entry->getEncryptedPassword());
    }

    public function validate(PasswordEntry $entry, User $actor): void
    {
        if (!$this->access->canValidatePassword($actor, $entry)) {
            throw new AccessDeniedException();
        }

        $entry->setIsValidated(true);
        $entry->setIsActive(true);
        $this->entityManager->flush();
    }

    public function toggleStatus(PasswordEntry $entry, User $actor): bool
    {
        if (!$this->access->canTogglePasswordStatus($actor, $entry)) {
            throw new AccessDeniedException();
        }

        $entry->setIsActive(!$entry->isActive());
        $this->entityManager->flush();

        return $entry->isActive();
    }

    public function delete(PasswordEntry $entry, User $actor): bool
    {
        if ($entry->isDeleted()) {
            throw new AccessDeniedException();
        }

        if (!$this->access->canDeletePasswords($actor)) {
            throw new AccessDeniedException();
        }

        if (!$this->access->isSuperAdmin($actor)) {
            $this->trashService->moveToTrash($entry, $actor);

            return true;
        }

        $this->entityManager->remove($entry);
        $this->entityManager->flush();

        return false;
    }

    private function assertPassword(string $plainPassword): void
    {
        if (trim($plainPassword) === '') {
            throw new \InvalidArgumentException('Le mot de passe est obligatoire.');
        }

        if (strlen($plainPassword) > 4096) {
            throw new \InvalidArgumentException('Le mot de passe est trop long.');
        }
    }
}
