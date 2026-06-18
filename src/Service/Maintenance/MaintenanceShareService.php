<?php

namespace App\Service\Maintenance;

use App\Entity\Intervenant;
use App\Entity\Intervention;
use App\Entity\MaintenanceContract;
use App\Entity\MaintenanceShare;
use App\Entity\User;
use App\Repository\IntervenantRepository;
use App\Repository\InterventionRepository;
use App\Repository\MaintenanceContractRepository;
use App\Repository\MaintenanceShareRepository;
use App\Repository\UserRepository;
use App\Service\SecurityAccessService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final readonly class MaintenanceShareService
{
    public const TYPE_INTERVENANT = 'intervenant';
    public const TYPE_CONTRACT = 'contract';
    public const TYPE_INTERVENTION = 'intervention';

    public function __construct(
        private MaintenanceShareRepository $shareRepository,
        private UserRepository $userRepository,
        private IntervenantRepository $intervenantRepository,
        private MaintenanceContractRepository $contractRepository,
        private InterventionRepository $interventionRepository,
        private EntityManagerInterface $entityManager,
        private SecurityAccessService $security,
    ) {
    }

    public function canView(User $actor, string $itemType, int $itemId): bool
    {
        if (!$actor->isActive() || !$this->security->canAccessModule($actor, 'maintenance')) {
            return false;
        }

        if ($this->security->isAdmin($actor)) {
            return true;
        }

        return $this->shareRepository->hasActiveShare($itemType, $itemId, $actor);
    }

    public function canViewObject(User $actor, object $item): bool
    {
        if (method_exists($item, 'isDeleted') && $item->isDeleted()) {
            return false;
        }

        $id = $this->itemId($item);

        return $id !== null && $this->canView($actor, $this->typeForObject($item), $id);
    }

    public function canShareObject(User $actor, object $item): bool
    {
        return $this->canManageShares($actor)
            && !(method_exists($item, 'isDeleted') && $item->isDeleted())
            && $this->itemId($item) !== null;
    }

    public function canManageShares(User $actor): bool
    {
        return $actor->isActive()
            && $this->security->isSuperAdmin($actor)
            && $this->security->canAccessModule($actor, 'maintenance');
    }

    /**
     * @template T of object
     *
     * @param list<T> $items
     *
     * @return list<T>
     */
    public function filterVisible(User $actor, string $itemType, array $items): array
    {
        if ($this->security->isAdmin($actor)) {
            return array_values($items);
        }

        $visibleIds = $this->shareRepository->findActiveItemIdsForUser($itemType, $actor);
        if ($visibleIds === []) {
            return [];
        }

        return array_values(array_filter($items, function (object $item) use ($visibleIds): bool {
            $id = $this->itemId($item);

            return $id !== null && isset($visibleIds[$id]);
        }));
    }

    /** @param list<object> $items */
    public function countActiveShares(string $itemType, array $items): array
    {
        $itemIds = [];
        foreach ($items as $item) {
            $id = $this->itemId($item);
            if ($id !== null) {
                $itemIds[] = $id;
            }
        }

        return $this->shareRepository->countActiveForItems($itemType, $itemIds);
    }

    public function ensureCreatorShare(string $itemType, int $itemId, User $actor): void
    {
        if ($this->security->isAdmin($actor)) {
            return;
        }

        $share = $this->shareRepository->findFor($itemType, $itemId, $actor) ?? (new MaintenanceShare())
            ->setItemType($itemType)
            ->setItemId($itemId)
            ->setUser($actor)
            ->setCreatedBy($actor);

        $share
            ->setCanView(true)
            ->setIsActive(true);

        $this->entityManager->persist($share);
        $this->entityManager->flush();
    }

    /** @return list<array{user: User, canView: bool}> */
    public function getShareMatrix(object $item, User $actor): array
    {
        $this->assertCanShare($item, $actor);
        $itemType = $this->typeForObject($item);
        $itemId = $this->itemId($item);
        if ($itemId === null) {
            throw new NotFoundHttpException('Element introuvable.');
        }

        $matrix = [];
        foreach ($this->userRepository->findActiveUsers() as $user) {
            if ($user === $actor || $this->security->isAdmin($user)) {
                continue;
            }

            $share = $this->shareRepository->findFor($itemType, $itemId, $user);
            $matrix[] = [
                'user' => $user,
                'canView' => $share instanceof MaintenanceShare && $share->isActive() && $share->canView(),
            ];
        }

        return $matrix;
    }

    /**
     * @param list<array{userId: int, canView: bool}> $items
     */
    public function synchronize(object $item, array $items, User $actor): void
    {
        $this->assertCanShare($item, $actor);
        $itemType = $this->typeForObject($item);
        $itemId = $this->itemId($item);
        if ($itemId === null) {
            throw new NotFoundHttpException('Element introuvable.');
        }

        $requested = [];
        foreach ($items as $itemPayload) {
            $userId = (int) ($itemPayload['userId'] ?? 0);
            $canView = filter_var($itemPayload['canView'] ?? false, FILTER_VALIDATE_BOOL);
            if ($userId <= 0 || !$canView) {
                continue;
            }

            $user = $this->userRepository->find($userId);
            if (!$user instanceof User || !$user->isActive() || $this->security->isAdmin($user)) {
                continue;
            }

            $share = $this->shareRepository->findFor($itemType, $itemId, $user) ?? (new MaintenanceShare())
                ->setItemType($itemType)
                ->setItemId($itemId)
                ->setUser($user)
                ->setCreatedBy($actor);

            $share
                ->setCanView(true)
                ->setIsActive(true);
            $this->entityManager->persist($share);
            $requested[$userId] = true;
        }

        foreach ($this->shareRepository->findForItem($itemType, $itemId) as $share) {
            $userId = $share->getUser()?->getId();
            if ($userId !== null && !isset($requested[$userId])) {
                $share->setIsActive(false);
            }
        }

        $this->entityManager->flush();
    }

    public function removeSharesFor(object $item): void
    {
        $itemId = $this->itemId($item);
        if ($itemId === null) {
            return;
        }

        foreach ($this->shareRepository->findForItem($this->typeForObject($item), $itemId) as $share) {
            $this->entityManager->remove($share);
        }
    }

    public function resolve(string $itemType, int $itemId): object
    {
        $item = match ($itemType) {
            self::TYPE_INTERVENANT => $this->intervenantRepository->find($itemId),
            self::TYPE_CONTRACT => $this->contractRepository->find($itemId),
            self::TYPE_INTERVENTION => $this->interventionRepository->find($itemId),
            default => null,
        };

        if (!is_object($item) || (method_exists($item, 'isDeleted') && $item->isDeleted())) {
            throw new NotFoundHttpException('Element introuvable.');
        }

        return $item;
    }

    public function titleFor(object $item): string
    {
        return match (true) {
            $item instanceof Intervenant => $item->getDisplayLabel(),
            $item instanceof MaintenanceContract => trim((string) ($item->getReference().' - '.$item->getCustomerName())),
            $item instanceof Intervention => trim((string) ($item->getReference().' - '.$item->getTitle())),
            default => 'Element maintenance',
        };
    }

    public function labelFor(object $item): string
    {
        return match (true) {
            $item instanceof Intervenant => 'cet intervenant',
            $item instanceof MaintenanceContract => 'ce contrat',
            $item instanceof Intervention => 'cette intervention',
            default => 'cet élément',
        };
    }

    private function assertCanShare(object $item, User $actor): void
    {
        if (!$this->canShareObject($actor, $item)) {
            throw new AccessDeniedException();
        }
    }

    private function typeForObject(object $item): string
    {
        return match (true) {
            $item instanceof Intervenant => self::TYPE_INTERVENANT,
            $item instanceof MaintenanceContract => self::TYPE_CONTRACT,
            $item instanceof Intervention => self::TYPE_INTERVENTION,
            default => throw new \InvalidArgumentException('Type de maintenance invalide.'),
        };
    }

    private function itemId(object $item): ?int
    {
        return method_exists($item, 'getId') ? $item->getId() : null;
    }
}
