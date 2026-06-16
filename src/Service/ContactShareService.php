<?php

namespace App\Service;

use App\Entity\Contact;
use App\Entity\ContactShare;
use App\Entity\User;
use App\Repository\ContactShareRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final readonly class ContactShareService
{
    public function __construct(
        private ContactShareRepository $shareRepository,
        private UserRepository $userRepository,
        private EntityManagerInterface $entityManager,
        private SecurityAccessService $access,
        private ContactPermissionService $permission,
    ) {
    }

    /** @return list<array{user: User, canView: bool}> */
    public function getShareMatrix(Contact $contact, User $actor): array
    {
        $this->assertCanShare($contact, $actor);
        $matrix = [];

        foreach ($this->userRepository->findActiveUsers() as $user) {
            if ($user === $actor || $this->access->isAdmin($user)) {
                continue;
            }

            $share = $this->shareRepository->findFor($contact, $user);
            $matrix[] = [
                'user' => $user,
                'canView' => $share instanceof ContactShare && $share->isActive() && $share->canView(),
            ];
        }

        return $matrix;
    }

    /**
     * @param list<array{userId: int, canView: bool}> $items
     */
    public function synchronize(Contact $contact, array $items, User $actor): void
    {
        $this->assertCanShare($contact, $actor);
        $requested = [];

        foreach ($items as $item) {
            $userId = (int) ($item['userId'] ?? 0);
            $canView = filter_var($item['canView'] ?? false, FILTER_VALIDATE_BOOL);
            if ($userId <= 0 || !$canView) {
                continue;
            }

            $user = $this->userRepository->find($userId);
            if (!$user instanceof User || !$user->isActive() || $this->access->isAdmin($user)) {
                continue;
            }

            $share = $this->shareRepository->findFor($contact, $user) ?? (new ContactShare())
                ->setContact($contact)
                ->setUser($user)
                ->setCreatedBy($actor);

            $share
                ->setCanView(true)
                ->setIsActive(true);
            $this->entityManager->persist($share);
            $requested[$userId] = true;
        }

        foreach ($contact->getShares()->toArray() as $share) {
            $userId = $share->getUser()?->getId();
            if ($userId !== null && !isset($requested[$userId])) {
                $share->setIsActive(false);
            }
        }

        $this->entityManager->flush();
    }

    private function assertCanShare(Contact $contact, User $actor): void
    {
        if (!$this->permission->canShare($actor, $contact)) {
            throw new AccessDeniedException();
        }
    }
}
