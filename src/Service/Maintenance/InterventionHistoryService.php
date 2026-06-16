<?php

namespace App\Service\Maintenance;

use App\Entity\Intervention;
use App\Entity\InterventionHistory;
use App\Entity\User;
use App\Repository\InterventionHistoryRepository;
use Doctrine\ORM\EntityManagerInterface;

final readonly class InterventionHistoryService
{
    public function __construct(
        private InterventionHistoryRepository $repository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function add(Intervention $intervention, string $action, User $actor, ?string $oldStatus = null, ?string $newStatus = null, ?string $comment = null, bool $flush = false): void
    {
        $history = (new InterventionHistory())
            ->setIntervention($intervention)
            ->setAction($action)
            ->setOldStatus($oldStatus)
            ->setNewStatus($newStatus)
            ->setComment($comment)
            ->setCreatedBy($actor);

        $this->entityManager->persist($history);
        if ($flush) {
            $this->entityManager->flush();
        }
    }

    /** @return list<InterventionHistory> */
    public function getHistory(Intervention $intervention): array
    {
        return $this->repository->findForIntervention($intervention);
    }
}
