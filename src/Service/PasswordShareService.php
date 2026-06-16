<?php

namespace App\Service;

use App\Entity\PasswordEntry;
use App\Entity\PasswordShare;
use App\Entity\User;
use App\Repository\PasswordShareRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final readonly class PasswordShareService
{
    public function __construct(
        private PasswordShareRepository $shareRepository,
        private UserRepository $userRepository,
        private EntityManagerInterface $entityManager,
        private SecurityAccessService $access,
    ) {
    }

    /** @return list<array{user: User, canView: bool, canEditPassword: bool}> */
    public function getShareMatrix(PasswordEntry $entry, User $actor): array
    {
        $this->assertCanShare($actor, $entry);
        $matrix = [];

        foreach ($this->userRepository->findActiveUsers() as $user) {
            if ($user === $actor || $this->access->isAdmin($user)) {
                continue;
            }

            $share = $this->shareRepository->findFor($entry, $user);
            $matrix[] = [
                'user' => $user,
                'canView' => $share?->canView() ?? false,
                'canEditPassword' => $share?->canEditPassword() ?? false,
            ];
        }

        return $matrix;
    }

    /**
     * @param list<array{userId: int, canView: bool, canEditPassword: bool}> $items
     */
    public function synchronize(PasswordEntry $entry, array $items, User $actor): void
    {
        $this->assertCanShare($actor, $entry);
        $requested = [];

        foreach ($items as $item) {
            $userId = (int) ($item['userId'] ?? 0);
            if ($userId <= 0) {
                continue;
            }

            $user = $this->userRepository->find($userId);
            if (!$user instanceof User || !$user->isActive() || $this->access->isAdmin($user)) {
                continue;
            }

            $canView = filter_var($item['canView'] ?? false, FILTER_VALIDATE_BOOL);
            $canEdit = filter_var($item['canEditPassword'] ?? false, FILTER_VALIDATE_BOOL);
            if (!$canView) {
                continue;
            }

            $share = $this->shareRepository->findFor($entry, $user) ?? (new PasswordShare())
                ->setPasswordEntry($entry)
                ->setUser($user);

            $share->setCanView(true)->setCanEditPassword($canEdit);
            $this->entityManager->persist($share);
            $requested[$userId] = true;
        }

        foreach ($entry->getShares()->toArray() as $share) {
            $userId = $share->getUser()?->getId();
            if ($userId !== null && !isset($requested[$userId])) {
                $this->entityManager->remove($share);
            }
        }

        $this->entityManager->flush();
    }

    public function remove(PasswordShare $share, User $actor): void
    {
        $entry = $share->getPasswordEntry();
        if (!$entry instanceof PasswordEntry) {
            throw new AccessDeniedException();
        }

        $this->assertCanShare($actor, $entry);
        $this->entityManager->remove($share);
        $this->entityManager->flush();
    }

    public function canEditOnlyPassword(PasswordEntry $entry, User $user): bool
    {
        return $this->access->canEditPasswordValue($user, $entry);
    }

    private function assertCanShare(User $actor, PasswordEntry $entry): void
    {
        if (!$this->access->canSharePassword($actor, $entry)) {
            throw new AccessDeniedException();
        }
    }
}
