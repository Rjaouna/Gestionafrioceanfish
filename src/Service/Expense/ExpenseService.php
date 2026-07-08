<?php

namespace App\Service\Expense;

use App\Entity\Expense;
use App\Entity\ExpenseShare;
use App\Entity\User;
use App\Repository\ExpenseRepository;
use App\Service\Trash\TrashService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final readonly class ExpenseService
{
    public function __construct(
        private ExpenseRepository $repository,
        private EntityManagerInterface $entityManager,
        private ExpenseAccessService $access,
        private ExpenseCalculatorService $calculator,
        private ExpenseDocumentService $documentService,
        private TrashService $trashService,
    ) {
    }

    /**
     * @param array<string, mixed> $filters
     *
     * @return array{items: list<Expense>, total: int, page: int, pages: int, perPage: int}
     */
    public function search(User $actor, array $filters = [], int $page = 1, int $perPage = 12): array
    {
        if (!$this->access->canAccess($actor)) {
            throw new AccessDeniedException();
        }

        $page = max(1, $page);
        $perPage = max(1, min(48, $perPage));
        $admin = $this->access->isAdmin($actor);
        $total = $this->repository->countVisible($actor, $admin, $filters);
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min($page, $pages);

        return [
            'items' => $this->repository->searchVisible($actor, $admin, $filters, $page, $perPage),
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
            'perPage' => $perPage,
        ];
    }

    public function create(Expense $expense, User $actor): Expense
    {
        if (!$this->access->canCreate($actor)) {
            throw new AccessDeniedException();
        }

        $this->prepare($expense);
        $expense
            ->setStatus(Expense::STATUS_DRAFT)
            ->setIsActive(true)
            ->setCreatedBy($actor);

        if (!$this->access->isAdmin($actor)) {
            $expense->addShare(
                (new ExpenseShare())
                    ->setUser($actor)
                    ->setCanView(true)
                    ->setIsActive(true)
                    ->setCreatedBy($actor),
            );
        }

        $this->entityManager->persist($expense);
        $this->entityManager->flush();

        return $expense;
    }

    public function update(Expense $expense, User $actor): Expense
    {
        if (!$this->access->canEdit($actor, $expense)) {
            throw new AccessDeniedException();
        }

        $this->prepare($expense);
        $this->entityManager->flush();

        return $expense;
    }

    public function toggleArchive(Expense $expense, User $actor): bool
    {
        if (!$this->access->canArchive($actor, $expense)) {
            throw new AccessDeniedException();
        }

        $expense->setIsActive(!$expense->isActive());
        $this->entityManager->flush();

        return $expense->isActive();
    }

    public function delete(Expense $expense, User $actor): bool
    {
        if (!$this->access->canDelete($actor, $expense)) {
            throw new AccessDeniedException();
        }

        if (!$this->access->isSuperAdmin($actor)) {
            $this->trashService->moveToTrash($expense, $actor);

            return true;
        }

        $this->documentService->deleteFilesForExpense($expense);
        $this->entityManager->remove($expense);
        $this->entityManager->flush();

        return false;
    }

    /**
     * @param array<string, mixed> $filters
     *
     * @return array<string, mixed>
     */
    public function stats(User $actor, array $filters = []): array
    {
        if (!$this->access->canAccess($actor)) {
            throw new AccessDeniedException();
        }

        $admin = $this->access->isAdmin($actor);
        $filteredTotal = $this->repository->sumVisible($actor, $admin, $filters);
        $filteredCount = $this->repository->countVisible($actor, $admin, $filters);

        return [
            'filtered_total' => $filteredTotal,
            'filtered_count' => $filteredCount,
            'filtered_average' => $filteredCount > 0 ? $filteredTotal / $filteredCount : 0,
            'filtered_paid_total' => $this->repository->sumVisible($actor, $admin, array_merge($filters, ['status' => Expense::STATUS_PAID])),
            'month_total' => $this->repository->sumByStatus($actor, $admin, null, true),
            'pending_count' => $this->repository->countByStatus($actor, $admin, Expense::STATUS_PENDING),
            'paid_total' => $this->repository->sumByStatus($actor, $admin, Expense::STATUS_PAID),
            'refused_count' => $this->repository->countByStatus($actor, $admin, Expense::STATUS_REFUSED),
            'validated_count' => $this->repository->countByStatus($actor, $admin, Expense::STATUS_VALIDATED),
            'categories' => $this->repository->totalsByCategory($actor, $admin, $filters),
        ];
    }

    /** @param array<string, mixed> $filters */
    public function exportCsv(User $actor, array $filters = []): string
    {
        $result = $this->search($actor, $filters, 1, 5000);
        $handle = fopen('php://temp', 'r+');
        if ($handle === false) {
            throw new \RuntimeException('Impossible de préparer l’export.');
        }

        fputcsv($handle, ['Référence', 'Date', 'Libellé', 'Catégorie', 'Fournisseur', 'Montant HT', 'TVA', 'Montant TTC', 'Statut'], ';');
        foreach ($result['items'] as $expense) {
            fputcsv($handle, [
                $expense->getReference(),
                $expense->getExpenseDate()?->format('d/m/Y'),
                $expense->getTitle(),
                $expense->getCategory()?->getName() ?? '',
                $expense->getSupplierName(),
                $expense->getAmountHt(),
                $expense->getVatAmount(),
                $expense->getAmountTtc(),
                $expense->getStatusLabel(),
            ], ';');
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return (string) $csv;
    }

    private function prepare(Expense $expense): void
    {
        if (!$expense->getReference()) {
            $expense->setReference($this->nextReference());
        }

        $this->calculator->applyTotals($expense);
    }

    private function nextReference(): string
    {
        $today = new \DateTimeImmutable('today');
        $prefix = 'DEP-'.$today->format('Ymd');

        return sprintf('%s-%03d', $prefix, $this->repository->nextReferenceNumber($prefix));
    }
}
