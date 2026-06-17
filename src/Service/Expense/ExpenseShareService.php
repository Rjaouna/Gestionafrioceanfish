<?php

namespace App\Service\Expense;

use App\Entity\Expense;
use App\Entity\ExpenseShare;
use App\Entity\User;
use App\Repository\ExpenseShareRepository;
use App\Repository\UserRepository;
use App\Service\SecurityAccessService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final readonly class ExpenseShareService
{
    public function __construct(
        private ExpenseShareRepository $shareRepository,
        private UserRepository $userRepository,
        private EntityManagerInterface $entityManager,
        private SecurityAccessService $security,
        private ExpenseAccessService $access,
    ) {
    }

    /** @return list<array{user: User, canView: bool}> */
    public function getShareMatrix(Expense $expense, User $actor): array
    {
        $this->assertCanShare($expense, $actor);
        $matrix = [];

        foreach ($this->userRepository->findActiveUsers() as $user) {
            if ($user === $actor || $this->security->isAdmin($user)) {
                continue;
            }

            $share = $this->shareRepository->findFor($expense, $user);
            $matrix[] = [
                'user' => $user,
                'canView' => $share instanceof ExpenseShare && $share->isActive() && $share->canView(),
            ];
        }

        return $matrix;
    }

    /**
     * @param list<array{userId: int, canView: bool}> $items
     */
    public function synchronize(Expense $expense, array $items, User $actor): void
    {
        $this->assertCanShare($expense, $actor);
        $requested = [];

        foreach ($items as $item) {
            $userId = (int) ($item['userId'] ?? 0);
            $canView = filter_var($item['canView'] ?? false, FILTER_VALIDATE_BOOL);
            if ($userId <= 0 || !$canView) {
                continue;
            }

            $user = $this->userRepository->find($userId);
            if (!$user instanceof User || !$user->isActive() || $this->security->isAdmin($user)) {
                continue;
            }

            $share = $this->shareRepository->findFor($expense, $user) ?? (new ExpenseShare())
                ->setExpense($expense)
                ->setUser($user)
                ->setCreatedBy($actor);

            $share
                ->setCanView(true)
                ->setIsActive(true);
            $this->entityManager->persist($share);
            $requested[$userId] = true;
        }

        foreach ($expense->getShares()->toArray() as $share) {
            $userId = $share->getUser()?->getId();
            if ($userId !== null && !isset($requested[$userId])) {
                $share->setIsActive(false);
            }
        }

        $this->entityManager->flush();
    }

    private function assertCanShare(Expense $expense, User $actor): void
    {
        if (!$this->access->canShare($actor, $expense)) {
            throw new AccessDeniedException();
        }
    }
}
