<?php

namespace App\Service\FishYieldStudy;

use App\Entity\FishYieldStudy;
use App\Entity\User;
use App\Repository\FishYieldStudyRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final readonly class FishYieldStudyService
{
    public function __construct(
        private FishYieldStudyRepository $repository,
        private EntityManagerInterface $entityManager,
        private FishYieldStudyPermissionService $permission,
    ) {
    }

    /**
     * @param array<string, mixed> $filters
     *
     * @return array{items: list<FishYieldStudy>, total: int, page: int, pages: int, perPage: int, filters: array<string, mixed>}
     */
    public function search(User $actor, array $filters = [], int $page = 1, int $perPage = 15): array
    {
        if (!$this->permission->canAccess($actor)) {
            throw new AccessDeniedException();
        }

        $filters = $this->normalizeFilters($filters);
        $page = max(1, $page);
        $perPage = max(1, min(60, $perPage));
        $total = $this->repository->countWithFilters($filters);
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min($page, $pages);

        return [
            'items' => $this->repository->searchWithFilters($filters, $page, $perPage),
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
            'perPage' => $perPage,
            'filters' => $filters,
        ];
    }

    /** @return array<string, mixed> */
    public function filterChoices(User $actor): array
    {
        if (!$this->permission->canAccess($actor)) {
            throw new AccessDeniedException();
        }

        return [
            'clients' => $this->repository->distinctValues('clientName'),
            'species' => $this->repository->distinctValues('speciesName'),
            'sorts' => [
                'date' => 'Date',
                'reference' => 'Reference',
                'client' => 'Client',
                'species' => 'Espece',
            ],
        ];
    }

    public function create(FishYieldStudy $study, User $actor): FishYieldStudy
    {
        if (!$this->permission->canCreate($actor)) {
            throw new AccessDeniedException();
        }

        $this->prepare($study);
        $study->setCreatedBy($actor);
        $this->entityManager->persist($study);
        $this->entityManager->flush();

        return $study;
    }

    public function update(FishYieldStudy $study, User $actor): FishYieldStudy
    {
        if (!$this->permission->canEdit($actor, $study)) {
            throw new AccessDeniedException();
        }

        $this->prepare($study);
        $this->entityManager->flush();

        return $study;
    }

    public function delete(FishYieldStudy $study, User $actor): void
    {
        if (!$this->permission->canDelete($actor, $study)) {
            throw new AccessDeniedException();
        }

        $study
            ->setIsDeleted(true)
            ->setDeletedAt(new \DateTimeImmutable())
            ->setDeletedBy($actor)
            ->setDeleteReason('Suppression depuis le module etudes rendement poisson.');
        $this->entityManager->flush();
    }

    /** @param array<string, mixed> $filters */
    public function normalizeFilters(array $filters): array
    {
        $normalized = [
            'q' => trim((string) ($filters['q'] ?? '')),
            'dateFrom' => trim((string) ($filters['dateFrom'] ?? '')),
            'dateTo' => trim((string) ($filters['dateTo'] ?? '')),
            'clientName' => trim((string) ($filters['clientName'] ?? '')),
            'speciesName' => trim((string) ($filters['speciesName'] ?? '')),
            'sort' => trim((string) ($filters['sort'] ?? 'date')),
            'direction' => trim((string) ($filters['direction'] ?? 'desc')),
        ];

        foreach (['dateFrom', 'dateTo'] as $dateKey) {
            if ($normalized[$dateKey] !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $normalized[$dateKey])) {
                $normalized[$dateKey] = '';
            }
        }

        if (!in_array($normalized['sort'], ['date', 'reference', 'client', 'species'], true)) {
            $normalized['sort'] = 'date';
        }

        $normalized['direction'] = strtolower($normalized['direction']) === 'asc' ? 'asc' : 'desc';

        return $normalized;
    }

    private function prepare(FishYieldStudy $study): void
    {
        if ($study->getStudyDate() === null) {
            $study->setStudyDate(new \DateTimeImmutable('today'));
        }

        if (!$study->hasMixedFish()) {
            $study->setMixedFishName(null);
        }

        if ($study->getReference() === null || $study->getReference() === '') {
            $study->setReference($this->nextReference());
        }
    }

    private function nextReference(): string
    {
        $prefix = sprintf('ERP-%s-', (new \DateTimeImmutable())->format('Y'));
        $sequence = $this->repository->countByReferencePrefix($prefix) + 1;

        do {
            $reference = sprintf('%s%04d', $prefix, $sequence++);
        } while ($this->repository->findOneBy(['reference' => $reference]) instanceof FishYieldStudy);

        return $reference;
    }
}
