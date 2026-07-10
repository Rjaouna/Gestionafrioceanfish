<?php

namespace App\Service\Expense;

use App\Entity\CashFundTransaction;
use App\Entity\Expense;
use App\Entity\User;
use App\Repository\CashFundTransactionRepository;
use Doctrine\ORM\EntityManagerInterface;

final readonly class CashFundService
{
    public function __construct(
        private CashFundTransactionRepository $repository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @param array<string, mixed> $filters
     *
     * @return array{items: list<CashFundTransaction>, total: int, page: int, pages: int, perPage: int, filters: array<string, mixed>}
     */
    public function search(array $filters = [], int $page = 1, int $perPage = 20): array
    {
        $filters = $this->normalizeFilters($filters);
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
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

    /** @param array<string, mixed> $filters */
    public function stats(array $filters = []): array
    {
        $filters = $this->normalizeFilters($filters);

        return [
            'balance' => $this->repository->balance(),
            'period_balance' => $this->repository->balance($filters),
            'period_inflow' => $this->repository->totalInflow($filters),
            'period_outflow' => $this->repository->totalOutflow($filters),
            'period_count' => $this->repository->countSearch($filters),
        ];
    }

    public function fund(CashFundTransaction $transaction, User $actor): CashFundTransaction
    {
        $amount = abs($transaction->amountValue());
        if ($amount <= 0) {
            throw new \DomainException('Le montant de la cagnotte doit etre superieur a 0.');
        }

        $transaction
            ->setReference($this->nextReference())
            ->setType(CashFundTransaction::TYPE_FUNDING)
            ->setAmount($amount)
            ->setSourceName($transaction->getSourceName() ?: 'Patron')
            ->setCreatedBy($actor);

        $this->entityManager->persist($transaction);
        $this->entityManager->flush();

        return $transaction;
    }

    public function deductPaidExpense(Expense $expense, User $actor): ?CashFundTransaction
    {
        if ($this->repository->findExpensePayment($expense) instanceof CashFundTransaction) {
            return null;
        }

        $amount = (float) $expense->getAmountTtc();
        if ($amount <= 0) {
            throw new \DomainException('Le montant de la depense doit etre superieur a 0 pour deduire la cagnotte.');
        }

        $balance = $this->repository->balance();
        if ($balance + 0.001 < $amount) {
            throw new \DomainException(sprintf(
                'Solde cagnotte insuffisant : %.2f dh disponible, %.2f dh necessaire.',
                $balance,
                $amount,
            ));
        }

        $paymentMethod = $expense->getPaymentMethod();
        if (!isset(CashFundTransaction::PAYMENT_METHOD_LABELS[(string) $paymentMethod])) {
            $paymentMethod = CashFundTransaction::PAYMENT_METHOD_OTHER;
        }

        $transaction = (new CashFundTransaction())
            ->setReference($this->nextReference())
            ->setMovementDate($expense->getPaidAt() ?? new \DateTimeImmutable('today'))
            ->setType(CashFundTransaction::TYPE_EXPENSE_PAYMENT)
            ->setAmount(-$amount)
            ->setPaymentMethod($paymentMethod)
            ->setSourceName($expense->getSupplierName())
            ->setNotes(sprintf('Deduction automatique apres paiement de la depense %s.', $expense->getReference() ?? ''))
            ->setExpense($expense)
            ->setCreatedBy($actor);

        $this->entityManager->persist($transaction);

        return $transaction;
    }

    public function reversePaidExpense(Expense $expense, User $actor, string $reason): ?CashFundTransaction
    {
        $payment = $this->repository->findExpensePayment($expense);
        if (!$payment instanceof CashFundTransaction || $this->repository->hasExpenseReversal($expense)) {
            return null;
        }

        $transaction = (new CashFundTransaction())
            ->setReference($this->nextReference())
            ->setMovementDate(new \DateTimeImmutable('today'))
            ->setType(CashFundTransaction::TYPE_EXPENSE_REVERSAL)
            ->setAmount($payment->absoluteAmountValue())
            ->setPaymentMethod($payment->getPaymentMethod())
            ->setSourceName($payment->getSourceName())
            ->setNotes(trim($reason) !== '' ? $reason : 'Annulation automatique de la deduction cagnotte.')
            ->setExpense($expense)
            ->setCreatedBy($actor);

        $this->entityManager->persist($transaction);

        return $transaction;
    }

    public function deleteFunding(CashFundTransaction $transaction, User $actor): void
    {
        if ($transaction->getType() !== CashFundTransaction::TYPE_FUNDING) {
            throw new \DomainException('Seules les alimentations saisies manuellement peuvent etre supprimees.');
        }

        $balanceAfterDelete = $this->repository->balance() - $transaction->amountValue();
        if ($balanceAfterDelete < -0.001) {
            throw new \DomainException('Impossible de supprimer cette alimentation : la cagnotte deviendrait negative.');
        }

        $transaction
            ->setIsDeleted(true)
            ->setDeletedAt(new \DateTimeImmutable())
            ->setDeletedBy($actor)
            ->setDeleteReason('Suppression alimentation cagnotte');
        $this->entityManager->flush();
    }

    private function nextReference(): string
    {
        $today = new \DateTimeImmutable('today');
        $prefix = 'CAG-'.$today->format('Ymd');

        return sprintf('%s-%03d', $prefix, $this->repository->nextReferenceNumber($prefix));
    }

    /** @param array<string, mixed> $filters */
    private function normalizeFilters(array $filters): array
    {
        $type = trim((string) ($filters['type'] ?? ''));
        if ($type !== '' && !isset(CashFundTransaction::TYPE_LABELS[$type])) {
            $type = '';
        }

        return [
            'q' => trim((string) ($filters['q'] ?? '')),
            'type' => $type,
            'dateFrom' => trim((string) ($filters['dateFrom'] ?? '')),
            'dateTo' => trim((string) ($filters['dateTo'] ?? '')),
        ];
    }
}
