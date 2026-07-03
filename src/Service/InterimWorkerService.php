<?php

namespace App\Service;

use App\Entity\InterimWorker;
use App\Entity\InterimWorkerAction;
use App\Entity\InterimWorkerDocument;
use App\Entity\User;
use App\Repository\InterimWorkerRepository;
use App\Service\Trash\TrashService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final readonly class InterimWorkerService
{
    public function __construct(
        private InterimWorkerRepository $repository,
        private EntityManagerInterface $entityManager,
        private InterimWorkerPermissionService $permission,
        private InterimWorkerStorageService $storage,
        private TrashService $trashService,
    ) {
    }

    /**
     * @param array<string, mixed> $filters
     *
     * @return array{items: list<InterimWorker>, total: int, page: int, pages: int, perPage: int, filters: array<string, mixed>}
     */
    public function search(User $actor, array $filters = [], int $page = 1, int $perPage = 12): array
    {
        if (!$this->permission->canAccess($actor)) {
            throw new AccessDeniedException();
        }

        $filters = $this->normalizeFilters($filters);
        $page = max(1, $page);
        $perPage = max(1, min(48, $perPage));
        $total = $this->repository->countVisible($filters);
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min($page, $pages);

        return [
            'items' => $this->repository->searchVisible($filters, $page, $perPage),
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
            'perPage' => $perPage,
            'filters' => $filters,
        ];
    }

    /** @return array{positions: list<string>, agencies: list<string>, workerTypes: array<string, string>, familySituations: array<string, string>, statuses: array<string, string>} */
    public function filterChoices(User $actor): array
    {
        if (!$this->permission->canAccess($actor)) {
            throw new AccessDeniedException();
        }

        return [
            'positions' => $this->positionChoices(),
            'agencies' => $this->repository->distinctValues('tempAgency'),
            'workerTypes' => InterimWorker::TYPE_LABELS,
            'familySituations' => InterimWorker::FAMILY_LABELS,
            'statuses' => InterimWorker::STATUS_LABELS,
        ];
    }

    /** @param list<UploadedFile> $documents */
    public function create(InterimWorker $worker, ?UploadedFile $photo, array $documents, User $actor): InterimWorker
    {
        if (!$this->permission->canCreate($actor)) {
            throw new AccessDeniedException();
        }

        $worker->setHireDate(new \DateTimeImmutable('today'));
        $this->prepareWorker($worker);
        $worker->setCreatedBy($actor);
        if ($photo instanceof UploadedFile) {
            $this->storage->replacePhoto($worker, $photo);
        }

        foreach ($documents as $file) {
            if ($file instanceof UploadedFile) {
                $worker->addDocument($this->storage->createDocument($file));
            }
        }

        $this->entityManager->persist($worker);
        $this->entityManager->flush();

        return $worker;
    }

    /** @param list<UploadedFile> $documents */
    public function update(InterimWorker $worker, ?UploadedFile $photo, array $documents, User $actor): InterimWorker
    {
        if (!$this->permission->canEdit($actor, $worker)) {
            throw new AccessDeniedException();
        }

        $previousStatus = $this->originalStatus($worker);
        $this->prepareWorker($worker);

        if ($photo instanceof UploadedFile) {
            $this->storage->replacePhoto($worker, $photo);
        }

        foreach ($documents as $file) {
            if ($file instanceof UploadedFile) {
                $worker->addDocument($this->storage->createDocument($file));
            }
        }

        if ($previousStatus !== $worker->getStatus()) {
            $actionAt = new \DateTimeImmutable();
            $worker->setLastStatusChangedAt($actionAt);
            if ($worker->getStatus() === InterimWorker::STATUS_ENDED && $worker->getMissionEndDate() === null) {
                $worker->setMissionEndDate(new \DateTimeImmutable('today'));
            }

            if ($worker->getStatus() === InterimWorker::STATUS_ENDED && $worker->getMissionEndReason() === null) {
                $worker->setMissionEndReason('Changement depuis le formulaire de la fiche.');
                $worker->setMissionEndedAt($actionAt);
            }

            if ($worker->getStatus() === InterimWorker::STATUS_DO_NOT_RECALL && $worker->getDoNotRecallReason() === null) {
                $worker->setDoNotRecallReason('Statut applique depuis le formulaire de la fiche.');
                $worker->setDoNotRecallAt($actionAt);
            }

            $this->recordAction(
                $worker,
                InterimWorkerAction::TYPE_STATUS_CHANGE,
                $previousStatus,
                $worker->getStatus(),
                'Changement depuis le formulaire de la fiche.',
                $actionAt,
                $actor,
            );
        }

        $this->entityManager->flush();

        return $worker;
    }

    public function endMission(InterimWorker $worker, \DateTimeImmutable $missionEndDate, string $reason, User $actor): void
    {
        if (!$this->permission->canEdit($actor, $worker)) {
            throw new AccessDeniedException();
        }

        $reason = trim($reason);
        if ($reason === '') {
            throw new \DomainException('Le motif de fin de mission est obligatoire.');
        }

        $previousStatus = $worker->getStatus();
        $worker
            ->setMissionEndDate($missionEndDate)
            ->setMissionEndReason($reason)
            ->setMissionEndedAt($missionEndDate)
            ->setLastStatusChangedAt($missionEndDate)
            ->setStatus(InterimWorker::STATUS_ENDED);

        $this->recordAction(
            $worker,
            InterimWorkerAction::TYPE_MISSION_END,
            $previousStatus,
            InterimWorker::STATUS_ENDED,
            $reason,
            $missionEndDate,
            $actor,
        );

        $this->entityManager->flush();
    }

    public function markDoNotRecall(InterimWorker $worker, \DateTimeImmutable $actionDate, string $reason, User $actor): void
    {
        if (!$this->permission->canEdit($actor, $worker)) {
            throw new AccessDeniedException();
        }

        $reason = trim($reason);
        if ($reason === '') {
            throw new \DomainException('Le motif grave est obligatoire.');
        }

        $previousStatus = $worker->getStatus();
        $worker
            ->setDoNotRecallAt($actionDate)
            ->setDoNotRecallReason($reason)
            ->setLastStatusChangedAt($actionDate)
            ->setStatus(InterimWorker::STATUS_DO_NOT_RECALL);

        $this->recordAction(
            $worker,
            InterimWorkerAction::TYPE_DO_NOT_RECALL,
            $previousStatus,
            InterimWorker::STATUS_DO_NOT_RECALL,
            $reason,
            $actionDate,
            $actor,
        );

        $this->entityManager->flush();
    }

    public function changeStatus(InterimWorker $worker, string $status, \DateTimeImmutable $actionDate, ?string $reason, User $actor): void
    {
        if (!$this->permission->canEdit($actor, $worker)) {
            throw new AccessDeniedException();
        }

        if (!isset(InterimWorker::STATUS_LABELS[$status])) {
            throw new \DomainException('Statut interimaire invalide.');
        }

        $reason = trim((string) $reason);
        if ($status === $worker->getStatus()) {
            throw new \DomainException('Ce statut est deja applique.');
        }

        if (in_array($status, [InterimWorker::STATUS_ENDED, InterimWorker::STATUS_DO_NOT_RECALL], true) && $reason === '') {
            throw new \DomainException('Un motif est obligatoire pour ce statut.');
        }

        $previousStatus = $worker->getStatus();
        if ($status === InterimWorker::STATUS_ENDED) {
            $missionEndDate = $worker->getMissionEndDate() ?? new \DateTimeImmutable($actionDate->format('Y-m-d'));
            $worker
                ->setMissionEndDate($missionEndDate)
                ->setMissionEndReason($reason)
                ->setMissionEndedAt($actionDate);
        }

        if ($status === InterimWorker::STATUS_DO_NOT_RECALL) {
            $worker
                ->setDoNotRecallAt($actionDate)
                ->setDoNotRecallReason($reason);
        }

        $worker
            ->setLastStatusChangedAt($actionDate)
            ->setStatus($status);

        $this->recordAction(
            $worker,
            InterimWorkerAction::TYPE_STATUS_CHANGE,
            $previousStatus,
            $status,
            $reason !== '' ? $reason : null,
            $actionDate,
            $actor,
        );

        $this->entityManager->flush();
    }

    public function delete(InterimWorker $worker, User $actor): bool
    {
        if (!$this->permission->canDelete($actor, $worker)) {
            throw new AccessDeniedException();
        }

        if (!$this->permission->isSuperAdmin($actor)) {
            $this->trashService->moveToTrash($worker, $actor);

            return true;
        }

        $this->storage->deleteFilesForWorker($worker);
        $this->entityManager->remove($worker);
        $this->entityManager->flush();

        return false;
    }

    public function deleteDocument(InterimWorkerDocument $document, User $actor): void
    {
        $worker = $document->getWorker();
        if (!$worker instanceof InterimWorker || !$this->permission->canEdit($actor, $worker)) {
            throw new AccessDeniedException();
        }

        $this->storage->deleteDocument($document);
        $this->entityManager->remove($document);
        $this->entityManager->flush();
    }

    private function originalStatus(InterimWorker $worker): string
    {
        $original = $this->entityManager->getUnitOfWork()->getOriginalEntityData($worker);

        return isset($original['status']) && is_string($original['status']) ? $original['status'] : $worker->getStatus();
    }

    private function prepareWorker(InterimWorker $worker): void
    {
        if ($worker->getRegistrationNumber() === null || $worker->getRegistrationNumber() === '') {
            $worker->setRegistrationNumber($this->nextRegistrationNumber());
        }

        if ($worker->getHireDate() === null) {
            $worker->setHireDate(new \DateTimeImmutable('today'));
        }

        if ($worker->getFamilySituation() === InterimWorker::FAMILY_SINGLE) {
            $worker->setChildrenCount(0);
        }

        if ($worker->getSignatureDate() === null) {
            $worker->setSignatureDate(new \DateTimeImmutable('today'));
        }
    }

    private function nextRegistrationNumber(): string
    {
        $prefix = sprintf('INT-%s-', (new \DateTimeImmutable())->format('Y'));
        $sequence = $this->repository->countByRegistrationPrefix($prefix) + 1;

        do {
            $registrationNumber = sprintf('%s%04d', $prefix, $sequence++);
        } while ($this->repository->findOneBy(['registrationNumber' => $registrationNumber]) instanceof InterimWorker);

        return $registrationNumber;
    }

    /** @return list<string> */
    public function positionChoices(): array
    {
        $positions = array_merge(InterimWorker::POSITION_CHOICES, $this->repository->distinctValues('position'));
        $positions = array_values(array_unique(array_filter(array_map(
            static fn (mixed $position): string => trim((string) $position),
            $positions,
        ))));
        natcasesort($positions);

        return array_values($positions);
    }

    private function recordAction(InterimWorker $worker, string $type, ?string $previousStatus, ?string $newStatus, ?string $reason, \DateTimeImmutable $actionAt, User $actor): void
    {
        $action = (new InterimWorkerAction())
            ->setActionType($type)
            ->setPreviousStatus($previousStatus)
            ->setNewStatus($newStatus)
            ->setReason($reason)
            ->setActionAt($actionAt)
            ->setPerformedBy($actor)
            ->setCreatedBy($actor);

        $worker->addAction($action);
        $this->entityManager->persist($action);
    }

    /** @param array<string, mixed> $filters */
    private function normalizeFilters(array $filters): array
    {
        $normalized = [
            'q' => trim((string) ($filters['q'] ?? '')),
            'position' => trim((string) ($filters['position'] ?? '')),
            'workerType' => trim((string) ($filters['workerType'] ?? '')),
            'familySituation' => trim((string) ($filters['familySituation'] ?? '')),
            'status' => trim((string) ($filters['status'] ?? '')),
            'hireDate' => trim((string) ($filters['hireDate'] ?? '')),
        ];

        if ($normalized['workerType'] !== '' && !isset(InterimWorker::TYPE_LABELS[$normalized['workerType']])) {
            $normalized['workerType'] = '';
        }

        if ($normalized['familySituation'] !== '' && !isset(InterimWorker::FAMILY_LABELS[$normalized['familySituation']])) {
            $normalized['familySituation'] = '';
        }

        if ($normalized['status'] !== '' && !isset(InterimWorker::STATUS_LABELS[$normalized['status']])) {
            $normalized['status'] = '';
        }

        if ($normalized['hireDate'] !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $normalized['hireDate'])) {
            $normalized['hireDate'] = '';
        }

        return $normalized;
    }
}
