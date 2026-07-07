<?php

namespace App\Service;

use App\Entity\InterimAttendance;
use App\Entity\InterimAttendanceRate;
use App\Entity\InterimWorker;
use App\Entity\User;
use App\Repository\InterimAttendanceRateRepository;
use App\Repository\InterimAttendanceRepository;
use Doctrine\ORM\EntityManagerInterface;

final readonly class InterimAttendanceService
{
    public function __construct(
        private InterimAttendanceRepository $attendanceRepository,
        private InterimAttendanceRateRepository $rateRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /** @return array{items: list<InterimAttendance>, total: int, page: int, pages: int, perPage: int, filters: array<string, mixed>, totals: array<string, int|float>} */
    public function search(array $filters = [], int $page = 1, int $perPage = 24): array
    {
        $filters = $this->normalizeFilters($filters);
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $total = $this->attendanceRepository->countSearch($filters);
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min($page, $pages);

        return [
            'items' => $this->attendanceRepository->search($filters, $page, $perPage),
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
            'perPage' => $perPage,
            'filters' => $filters,
            'totals' => $this->attendanceRepository->totals($filters),
        ];
    }

    public function hourlyDraft(InterimWorker $worker, ?\DateTimeImmutable $date = null): InterimAttendance
    {
        $date ??= new \DateTimeImmutable('today');
        $existing = $this->attendanceRepository->findHourlyForWorkerAndDate($worker, $date);
        if ($existing instanceof InterimAttendance) {
            return $existing;
        }

        return (new InterimAttendance())
            ->setWorker($worker)
            ->setMode(InterimAttendance::MODE_HOURLY)
            ->setAttendanceDate($date)
            ->setHourlyRate($this->defaultHourlyRate());
    }

    public function saveHourly(InterimWorker $worker, InterimAttendance $attendance, User $actor): InterimAttendance
    {
        $attendance
            ->setWorker($worker)
            ->setMode(InterimAttendance::MODE_HOURLY);

        $this->calculateHourly($attendance);
        $date = $attendance->getAttendanceDate();
        if (!$date instanceof \DateTimeImmutable) {
            throw new \DomainException('Date de pointage invalide.');
        }

        $target = $this->attendanceRepository->findHourlyForWorkerAndDate($worker, $date);
        if ($target instanceof InterimAttendance && $target->getId() !== $attendance->getId()) {
            $this->copyHourly($attendance, $target);
            $attendance = $target;
        }

        if ($attendance->getId() === null) {
            $attendance->setCreatedBy($actor);
            $this->entityManager->persist($attendance);
        } else {
            $attendance->setUpdatedBy($actor);
        }

        $this->entityManager->flush();

        return $attendance;
    }

    /** @return array{items: list<InterimAttendance>, totals: array{hours: float, amount: float, count: int}, month: string} */
    public function workerMonth(InterimWorker $worker, ?string $month = null): array
    {
        $monthDate = $this->monthFromString($month);
        $items = $this->attendanceRepository->findForWorkerMonth($worker, $monthDate);
        $hours = 0.0;
        $amount = 0.0;
        foreach ($items as $item) {
            $hours += $item->getTotalHoursValue();
            $amount += $item->getTotalAmountValue();
        }

        return [
            'items' => $items,
            'totals' => [
                'hours' => $hours,
                'amount' => $amount,
                'count' => count($items),
            ],
            'month' => $monthDate->format('Y-m'),
        ];
    }

    /** @return list<InterimAttendanceRate> */
    public function rates(): array
    {
        $this->ensureDefaultRates();

        return $this->rateRepository->ordered();
    }

    public function updateRate(InterimAttendanceRate $rate, User $actor): void
    {
        $rate->setUpdatedBy($actor);
        $this->entityManager->flush();
    }

    public function defaultHourlyRate(): float
    {
        $this->ensureDefaultRates();
        $rate = $this->rateRepository->findOneBy(['code' => InterimAttendanceRate::CODE_HOURLY_DEFAULT]);

        return $rate instanceof InterimAttendanceRate && $rate->isActive() ? $rate->getAmountValue() : 0.0;
    }

    private function calculateHourly(InterimAttendance $attendance): void
    {
        if (!$attendance->isMorningPresent()) {
            $attendance->setMorningStart(null)->setMorningEnd(null);
        }
        if (!$attendance->isAfternoonPresent()) {
            $attendance->setAfternoonStart(null)->setAfternoonEnd(null);
        }

        $hours = 0.0;
        $hours += $this->segmentHours($attendance->isMorningPresent(), $attendance->getMorningStart(), $attendance->getMorningEnd(), 'matin');
        $hours += $this->segmentHours($attendance->isAfternoonPresent(), $attendance->getAfternoonStart(), $attendance->getAfternoonEnd(), 'apres-midi');

        $attendance
            ->setTotalHours(round($hours, 2))
            ->setTotalAmount(round($hours * $attendance->getHourlyRateValue(), 2));
    }

    private function segmentHours(bool $present, ?\DateTimeImmutable $start, ?\DateTimeImmutable $end, string $label): float
    {
        if (!$present) {
            return 0.0;
        }

        if (!$start instanceof \DateTimeImmutable || !$end instanceof \DateTimeImmutable) {
            throw new \DomainException(sprintf('Renseignez le debut et la fin du creneau %s.', $label));
        }

        $startMinutes = ((int) $start->format('H')) * 60 + (int) $start->format('i');
        $endMinutes = ((int) $end->format('H')) * 60 + (int) $end->format('i');
        if ($endMinutes <= $startMinutes) {
            throw new \DomainException(sprintf('Le creneau %s est invalide : la fin doit etre apres le debut.', $label));
        }

        return ($endMinutes - $startMinutes) / 60;
    }

    private function copyHourly(InterimAttendance $source, InterimAttendance $target): void
    {
        $target
            ->setMorningPresent($source->isMorningPresent())
            ->setMorningStart($source->getMorningStart())
            ->setMorningEnd($source->getMorningEnd())
            ->setAfternoonPresent($source->isAfternoonPresent())
            ->setAfternoonStart($source->getAfternoonStart())
            ->setAfternoonEnd($source->getAfternoonEnd())
            ->setHourlyRate($source->getHourlyRate())
            ->setTotalHours($source->getTotalHours())
            ->setTotalAmount($source->getTotalAmount())
            ->setComment($source->getComment());
    }

    private function ensureDefaultRates(): void
    {
        $defaults = [
            InterimAttendanceRate::CODE_HOURLY_DEFAULT => ['Taux horaire par defaut', InterimAttendance::MODE_HOURLY, 'heure', '0.00'],
            InterimAttendanceRate::CODE_TASK_CLEANING => ['Tache nettoyage', InterimAttendance::MODE_TASK, 'nettoyage', '0.00'],
            InterimAttendanceRate::CODE_TASK_BOXING => ['Tache mise en caisse', InterimAttendance::MODE_TASK, 'caisse', '0.00'],
        ];

        $created = false;
        foreach ($defaults as $code => [$label, $mode, $unit, $amount]) {
            if ($this->rateRepository->findOneBy(['code' => $code]) instanceof InterimAttendanceRate) {
                continue;
            }

            $rate = (new InterimAttendanceRate())
                ->setCode($code)
                ->setLabel($label)
                ->setMode($mode)
                ->setUnitLabel($unit)
                ->setAmount($amount)
                ->setActive(true);
            $this->entityManager->persist($rate);
            $created = true;
        }

        if ($created) {
            $this->entityManager->flush();
        }
    }

    /** @return array<string, mixed> */
    private function normalizeFilters(array $filters): array
    {
        $normalized = [
            'q' => trim((string) ($filters['q'] ?? '')),
            'mode' => trim((string) ($filters['mode'] ?? '')),
            'dateFrom' => trim((string) ($filters['dateFrom'] ?? '')),
            'dateTo' => trim((string) ($filters['dateTo'] ?? '')),
        ];

        if ($normalized['mode'] !== '' && !isset(InterimAttendance::MODE_LABELS[$normalized['mode']])) {
            $normalized['mode'] = '';
        }

        foreach (['dateFrom', 'dateTo'] as $key) {
            if ($normalized[$key] !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $normalized[$key])) {
                $normalized[$key] = '';
            }
        }

        return $normalized;
    }

    private function monthFromString(?string $month): \DateTimeImmutable
    {
        if (is_string($month) && preg_match('/^\d{4}-\d{2}$/', $month)) {
            return new \DateTimeImmutable($month.'-01');
        }

        return new \DateTimeImmutable('first day of this month');
    }
}
