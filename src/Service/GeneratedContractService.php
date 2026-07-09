<?php

namespace App\Service;

use App\Entity\GeneratedContract;
use App\Entity\User;
use App\Repository\GeneratedContractRepository;
use Doctrine\ORM\EntityManagerInterface;

final readonly class GeneratedContractService
{
    public function __construct(
        private GeneratedContractRepository $repository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /** @param array<string, mixed> $filters */
    public function search(array $filters, int $page = 1, int $perPage = 20): array
    {
        $filters = $this->normalizeFilters($filters);
        $page = max(1, $page);
        $perPage = max(1, min(60, $perPage));
        $total = $this->repository->countSearch($filters);
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min($page, $pages);

        return [
            'items' => $this->repository->search($filters, $page, $perPage),
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
            'perPage' => $perPage,
            'filters' => $filters,
        ];
    }

    public function create(GeneratedContract $contract, User $actor): GeneratedContract
    {
        $this->prepare($contract);
        $contract
            ->setCreatedAt(new \DateTimeImmutable())
            ->setCreatedBy($actor)
            ->setStatus(GeneratedContract::STATUS_DRAFT);
        $this->entityManager->persist($contract);
        $this->entityManager->flush();

        return $contract;
    }

    public function update(GeneratedContract $contract, User $actor): GeneratedContract
    {
        $this->prepare($contract);
        $contract
            ->setUpdatedAt(new \DateTimeImmutable())
            ->setUpdatedBy($actor)
            ->setStatus(GeneratedContract::STATUS_DRAFT);
        $this->entityManager->flush();

        return $contract;
    }

    public function markGenerated(GeneratedContract $contract, User $actor): void
    {
        $contract
            ->setStatus(GeneratedContract::STATUS_GENERATED)
            ->setLastGeneratedAt(new \DateTimeImmutable())
            ->setLastGeneratedBy($actor)
            ->setUpdatedAt(new \DateTimeImmutable())
            ->setUpdatedBy($actor);
        $this->entityManager->flush();
    }

    public function delete(GeneratedContract $contract, User $actor): void
    {
        $contract
            ->setIsDeleted(true)
            ->setDeletedAt(new \DateTimeImmutable())
            ->setDeletedBy($actor)
            ->setDeleteReason('Suppression depuis le module contrats.');
        $this->entityManager->flush();
    }

    public function fileName(GeneratedContract $contract): string
    {
        $client = preg_replace('/[^A-Za-z0-9]+/', '-', strtoupper((string) $contract->getClientCompanyName())) ?: 'CLIENT';

        return sprintf('%s-%s.pdf', $contract->getReference(), trim($client, '-'));
    }

    private function prepare(GeneratedContract $contract): void
    {
        $contract->setContractType(GeneratedContract::TYPE_CONDITIONING);
        if (!$contract->getReference()) {
            $prefix = sprintf('CTR-COND-%s-', ($contract->getContractDate() ?? new \DateTimeImmutable())->format('Y'));
            $contract->setReference(sprintf('%s%04d', $prefix, $this->repository->nextReferenceNumber($prefix)));
        }
    }

    /** @param array<string, mixed> $filters */
    private function normalizeFilters(array $filters): array
    {
        return [
            'q' => trim((string) ($filters['q'] ?? '')),
            'type' => trim((string) ($filters['type'] ?? '')),
            'status' => trim((string) ($filters['status'] ?? '')),
            'dateFrom' => $this->validDate((string) ($filters['dateFrom'] ?? '')),
            'dateTo' => $this->validDate((string) ($filters['dateTo'] ?? '')),
        ];
    }

    private function validDate(string $value): string
    {
        $value = trim($value);

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : '';
    }
}
