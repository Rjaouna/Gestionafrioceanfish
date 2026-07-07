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

    /** @return array{filters: array<string, mixed>, totals: array<string, int|float>, workerSummaries: list<array<string, mixed>>, dailySummaries: list<array<string, mixed>>, items: list<InterimAttendance>} */
    public function details(array $filters = []): array
    {
        $filters = $this->normalizeFilters($filters);
        $items = $this->attendanceRepository->search($filters, 1, 10000);
        $totals = $this->attendanceRepository->totals($filters);
        $workerSummaries = [];
        $dailySummaries = [];

        foreach ($items as $item) {
            $worker = $item->getWorker();
            if (!$worker instanceof InterimWorker) {
                continue;
            }

            $workerKey = (string) $worker->getId();
            if (!isset($workerSummaries[$workerKey])) {
                $workerSummaries[$workerKey] = [
                    'worker' => $worker,
                    'count' => 0,
                    'hours' => 0.0,
                    'amount' => 0.0,
                    'hourlyCount' => 0,
                    'taskCount' => 0,
                    'fullDays' => 0,
                    'halfDays' => 0,
                    'absentDays' => 0,
                    'firstDate' => null,
                    'lastDate' => null,
                    'averageRate' => 0.0,
                ];
            }

            $date = $item->getAttendanceDate();
            $dateKey = $date?->format('Y-m-d') ?? 'sans-date';
            if (!isset($dailySummaries[$dateKey])) {
                $dailySummaries[$dateKey] = [
                    'date' => $date,
                    'count' => 0,
                    'hours' => 0.0,
                    'amount' => 0.0,
                    'workers' => [],
                ];
            }

            $hours = $item->getTotalHoursValue();
            $amount = $item->getTotalAmountValue();
            $workerSummaries[$workerKey]['count']++;
            $workerSummaries[$workerKey]['hours'] += $hours;
            $workerSummaries[$workerKey]['amount'] += $amount;
            $workerSummaries[$workerKey][$item->getMode() === InterimAttendance::MODE_TASK ? 'taskCount' : 'hourlyCount']++;

            if ($item->isMorningPresent() && $item->isAfternoonPresent()) {
                $workerSummaries[$workerKey]['fullDays']++;
            } elseif ($item->isMorningPresent() || $item->isAfternoonPresent()) {
                $workerSummaries[$workerKey]['halfDays']++;
            } else {
                $workerSummaries[$workerKey]['absentDays']++;
            }

            if ($date instanceof \DateTimeImmutable) {
                $firstDate = $workerSummaries[$workerKey]['firstDate'];
                $lastDate = $workerSummaries[$workerKey]['lastDate'];
                if (!$firstDate instanceof \DateTimeImmutable || $date < $firstDate) {
                    $workerSummaries[$workerKey]['firstDate'] = $date;
                }
                if (!$lastDate instanceof \DateTimeImmutable || $date > $lastDate) {
                    $workerSummaries[$workerKey]['lastDate'] = $date;
                }
            }

            $dailySummaries[$dateKey]['count']++;
            $dailySummaries[$dateKey]['hours'] += $hours;
            $dailySummaries[$dateKey]['amount'] += $amount;
            $dailySummaries[$dateKey]['workers'][$workerKey] = true;
        }

        foreach ($workerSummaries as &$summary) {
            $summary['averageRate'] = $summary['hours'] > 0 ? $summary['amount'] / $summary['hours'] : 0.0;
        }
        unset($summary);

        foreach ($dailySummaries as &$summary) {
            $summary['workersCount'] = count($summary['workers']);
            unset($summary['workers']);
        }
        unset($summary);

        $workerSummaries = array_values($workerSummaries);
        usort($workerSummaries, static fn (array $a, array $b): int => ($b['amount'] <=> $a['amount']) ?: strcasecmp($a['worker']->getFullName(), $b['worker']->getFullName()));

        $dailySummaries = array_values($dailySummaries);
        usort($dailySummaries, static function (array $a, array $b): int {
            $dateA = $a['date'] instanceof \DateTimeImmutable ? $a['date']->getTimestamp() : 0;
            $dateB = $b['date'] instanceof \DateTimeImmutable ? $b['date']->getTimestamp() : 0;

            return $dateB <=> $dateA;
        });

        $totals += [
            'workers' => count($workerSummaries),
            'days' => count($dailySummaries),
            'averageRate' => ($totals['hours'] ?? 0) > 0 ? ((float) $totals['amount'] / (float) $totals['hours']) : 0.0,
            'maxWorkerAmount' => $workerSummaries !== [] ? max(array_map(static fn (array $row): float => (float) $row['amount'], $workerSummaries)) : 0.0,
        ];

        return [
            'filters' => $filters,
            'totals' => $totals,
            'workerSummaries' => $workerSummaries,
            'dailySummaries' => $dailySummaries,
            'items' => $items,
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
