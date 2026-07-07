<?php

namespace App\Service;

use App\Entity\InterimPayment;
use App\Entity\InterimWorker;
use App\Entity\User;
use App\Repository\InterimPaymentRepository;
use App\Repository\InterimWorkerRepository;
use Doctrine\ORM\EntityManagerInterface;

final readonly class InterimPaymentService
{
    public function __construct(
        private InterimPaymentRepository $paymentRepository,
        private InterimWorkerRepository $workerRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /** @return array{items: list<InterimPayment>, total: int, page: int, pages: int, perPage: int, filters: array<string, mixed>, totals: array<string, int|float>} */
    public function search(array $filters = [], int $page = 1, int $perPage = 25): array
    {
        $filters = $this->normalizeFilters($filters);
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $total = $this->paymentRepository->countSearch($filters);
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min($page, $pages);

        return [
            'items' => $this->paymentRepository->search($filters, $page, $perPage),
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
            'perPage' => $perPage,
            'filters' => $filters,
            'totals' => $this->paymentRepository->totals($filters),
        ];
    }

    public function create(InterimPayment $payment, User $actor): InterimPayment
    {
        $this->prepare($payment);
        $payment
            ->setCreatedAt(new \DateTimeImmutable())
            ->setCreatedBy($actor);

        $this->entityManager->persist($payment);
        $this->entityManager->flush();

        return $payment;
    }

    public function update(InterimPayment $payment, User $actor): InterimPayment
    {
        $this->prepare($payment);
        $payment
            ->setUpdatedAt(new \DateTimeImmutable())
            ->setUpdatedBy($actor);

        $this->entityManager->flush();

        return $payment;
    }

    /** @return list<InterimWorker> */
    public function workerChoices(): array
    {
        return $this->workerRepository->createQueryBuilder('w')
            ->andWhere('w.isDeleted = false')
            ->orderBy('w.lastName', 'ASC')
            ->addOrderBy('w.firstName', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** @return array<string, mixed> */
    public function normalizeFilters(array $filters): array
    {
        $normalized = [
            'q' => trim((string) ($filters['q'] ?? '')),
            'workerId' => trim((string) ($filters['workerId'] ?? '')),
            'status' => trim((string) ($filters['status'] ?? '')),
            'paymentMethod' => trim((string) ($filters['paymentMethod'] ?? '')),
            'dateFrom' => trim((string) ($filters['dateFrom'] ?? '')),
            'dateTo' => trim((string) ($filters['dateTo'] ?? '')),
            'periodFrom' => trim((string) ($filters['periodFrom'] ?? '')),
            'periodTo' => trim((string) ($filters['periodTo'] ?? '')),
        ];

        if ($normalized['workerId'] !== '' && !ctype_digit($normalized['workerId'])) {
            $normalized['workerId'] = '';
        }

        if ($normalized['status'] !== '' && !isset(InterimPayment::STATUS_LABELS[$normalized['status']])) {
            $normalized['status'] = '';
        }

        if ($normalized['paymentMethod'] !== '' && !isset(InterimPayment::METHOD_LABELS[$normalized['paymentMethod']])) {
            $normalized['paymentMethod'] = '';
        }

        foreach (['dateFrom', 'dateTo', 'periodFrom', 'periodTo'] as $key) {
            if ($normalized[$key] !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $normalized[$key])) {
                $normalized[$key] = '';
            }
        }

        return $normalized;
    }

    private function prepare(InterimPayment $payment): void
    {
        if (!$payment->getWorker() instanceof InterimWorker) {
            throw new \DomainException('Selectionnez un interimaire.');
        }
        if (!$payment->getPaymentDate() instanceof \DateTimeImmutable) {
            throw new \DomainException('Indiquez la date de paiement.');
        }
        if ($payment->getAmountValue() <= 0) {
            throw new \DomainException('Le montant du paiement doit etre superieur a zero.');
        }
        if ($payment->getPeriodFrom() instanceof \DateTimeImmutable && $payment->getPeriodTo() instanceof \DateTimeImmutable && $payment->getPeriodTo() < $payment->getPeriodFrom()) {
            throw new \DomainException('La fin de periode ne peut pas etre avant le debut.');
        }
    }
}
