<?php

namespace App\Service;

use App\Entity\InterimAttendance;
use App\Entity\InterimAttendanceRate;
use App\Entity\InterimWorker;
use App\Entity\User;
use App\Repository\InterimAttendanceRateRepository;
use App\Repository\InterimAttendanceRepository;
use App\Repository\InterimWorkerRepository;
use Doctrine\ORM\EntityManagerInterface;

final readonly class InterimAttendanceService
{
    private const DEFAULT_CLEANING_RATE = 25.00;
    private const DEFAULT_BOXING_RATE = 2.00;

    public function __construct(
        private InterimAttendanceRepository $attendanceRepository,
        private InterimAttendanceRateRepository $rateRepository,
        private InterimWorkerRepository $workerRepository,
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
                    'hourlyAmount' => 0.0,
                    'taskAmount' => 0.0,
                    'taskKg' => 0.0,
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
                    'taskKg' => 0.0,
                    'workers' => [],
                ];
            }

            $hours = $item->getTotalHoursValue();
            $amount = $item->getTotalAmountValue();
            $taskKg = $item->getMode() === InterimAttendance::MODE_TASK ? $item->getTaskWeightKgValue() : 0.0;
            $workerSummaries[$workerKey]['count']++;
            $workerSummaries[$workerKey]['hours'] += $hours;
            $workerSummaries[$workerKey]['amount'] += $amount;
            $workerSummaries[$workerKey]['taskKg'] += $taskKg;
            $workerSummaries[$workerKey][$item->getMode() === InterimAttendance::MODE_TASK ? 'taskCount' : 'hourlyCount']++;
            if ($item->getMode() === InterimAttendance::MODE_TASK) {
                $workerSummaries[$workerKey]['taskAmount'] += $amount;
            } else {
                $workerSummaries[$workerKey]['hourlyAmount'] += $amount;
            }

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
            $dailySummaries[$dateKey]['taskKg'] += $taskKg;
            $dailySummaries[$dateKey]['workers'][$workerKey] = true;
        }

        foreach ($workerSummaries as &$summary) {
            $summary['averageRate'] = $summary['hours'] > 0 ? $summary['hourlyAmount'] / $summary['hours'] : 0.0;
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
            'taskKg' => array_sum(array_map(static fn (array $row): float => (float) $row['taskKg'], $workerSummaries)),
            'taskAmount' => array_sum(array_map(static fn (array $row): float => (float) $row['taskAmount'], $workerSummaries)),
            'hourlyAmount' => array_sum(array_map(static fn (array $row): float => (float) $row['hourlyAmount'], $workerSummaries)),
            'averageRate' => ($totals['hours'] ?? 0) > 0 ? (array_sum(array_map(static fn (array $row): float => (float) $row['hourlyAmount'], $workerSummaries)) / (float) $totals['hours']) : 0.0,
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

    /** @return array{filters: array<string, string>, dateFrom: \DateTimeImmutable, dateTo: \DateTimeImmutable, rows: list<array<string, int|float|string>>, totals: array<string, int|float>, periodLabel: string} */
    public function journal(array $filters = []): array
    {
        $filters = $this->normalizeJournalFilters($filters);
        $dateFrom = new \DateTimeImmutable($filters['dateFrom']);
        $dateTo = new \DateTimeImmutable($filters['dateTo']);

        if ($dateTo < $dateFrom) {
            [$dateFrom, $dateTo] = [$dateTo, $dateFrom];
            $filters['dateFrom'] = $dateFrom->format('Y-m-d');
            $filters['dateTo'] = $dateTo->format('Y-m-d');
        }

        $rows = [];
        $totals = [
            'workers' => 0,
            'lines' => 0,
            'hours' => 0.0,
            'cleaningKg' => 0.0,
            'boxingKg' => 0.0,
            'taskKg' => 0.0,
            'earnedAmount' => 0.0,
        ];

        foreach ($this->attendanceRepository->journalByWorker($dateFrom, $dateTo) as $row) {
            $cleaningKg = (float) ($row['cleaningKg'] ?? 0);
            $boxingKg = (float) ($row['boxingKg'] ?? 0);
            $earnedAmount = (float) ($row['earnedAmount'] ?? 0);
            $hours = (float) ($row['hours'] ?? 0);
            $lineCount = (int) ($row['lineCount'] ?? 0);

            $rows[] = [
                'workerId' => (int) $row['workerId'],
                'fullName' => trim((string) ($row['lastName'] ?? '').' '.(string) ($row['firstName'] ?? '')),
                'registrationNumber' => (string) ($row['registrationNumber'] ?? ''),
                'hours' => $hours,
                'cleaningKg' => $cleaningKg,
                'boxingKg' => $boxingKg,
                'taskKg' => $cleaningKg + $boxingKg,
                'earnedAmount' => $earnedAmount,
                'lineCount' => $lineCount,
            ];

            $totals['workers']++;
            $totals['lines'] += $lineCount;
            $totals['hours'] += $hours;
            $totals['cleaningKg'] += $cleaningKg;
            $totals['boxingKg'] += $boxingKg;
            $totals['taskKg'] += $cleaningKg + $boxingKg;
            $totals['earnedAmount'] += $earnedAmount;
        }

        $rows = $this->withJournalPerformanceLevels($rows, 'cleaningKg', 'cleaningPerformance');
        $rows = $this->withJournalPerformanceLevels($rows, 'boxingKg', 'boxingPerformance');
        $rows = $this->withJournalPerformanceLevels($rows, 'earnedAmount', 'earnedPerformance');

        return [
            'filters' => $filters,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'rows' => $rows,
            'totals' => $totals,
            'periodLabel' => $dateFrom->format('d/m/Y') === $dateTo->format('d/m/Y')
                ? $dateFrom->format('d/m/Y')
                : sprintf('%s au %s', $dateFrom->format('d/m/Y'), $dateTo->format('d/m/Y')),
        ];
    }

    /**
     * Classifies distinct positive values into three relative performance tiers.
     *
     * @param list<array<string, int|float|string>> $rows
     *
     * @return list<array<string, int|float|string>>
     */
    private function withJournalPerformanceLevels(array $rows, string $valueKey, string $levelKey): array
    {
        $values = [];
        foreach ($rows as $row) {
            $value = (float) ($row[$valueKey] ?? 0);
            if ($value > 0) {
                $values[(string) $value] = $value;
            }
        }

        rsort($values, SORT_NUMERIC);
        $lastIndex = count($values) - 1;
        $levels = [];

        foreach ($values as $index => $value) {
            $position = $lastIndex > 0 ? $index / $lastIndex : 0.0;
            $levels[(string) $value] = match (true) {
                $position <= 1 / 3 => 'high',
                $position <= 2 / 3 => 'medium',
                default => 'low',
            };
        }

        foreach ($rows as &$row) {
            $value = (float) ($row[$valueKey] ?? 0);
            $row[$levelKey] = $value > 0 ? ($levels[(string) $value] ?? 'medium') : 'none';
        }
        unset($row);

        return $rows;
    }

    /** @return array{filters: array<string, string>, attendanceDate: \DateTimeImmutable, workers: list<InterimWorker>, generatedAt: \DateTimeImmutable} */
    public function attendanceSheet(array $filters = []): array
    {
        $date = trim((string) ($filters['date'] ?? ''));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $date = (new \DateTimeImmutable('today'))->format('Y-m-d');
        }

        return [
            'filters' => ['date' => $date],
            'attendanceDate' => new \DateTimeImmutable($date),
            'workers' => $this->workerRepository->findForAttendanceSheet(),
            'generatedAt' => new \DateTimeImmutable(),
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

    public function taskDraft(InterimWorker $worker, ?\DateTimeImmutable $date = null): InterimAttendance
    {
        $date ??= new \DateTimeImmutable('today');

        return (new InterimAttendance())
            ->setWorker($worker)
            ->setMode(InterimAttendance::MODE_TASK)
            ->setAttendanceDate($date)
            ->setTaskType(InterimAttendance::TASK_CLEANING_ANCHOVY)
            ->setTaskQuantity(1);
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

    public function saveTask(InterimWorker $worker, InterimAttendance $attendance, User $actor): InterimAttendance
    {
        $attendance
            ->setWorker($worker)
            ->setMode(InterimAttendance::MODE_TASK)
            ->setMorningPresent(false)
            ->setMorningStart(null)
            ->setMorningEnd(null)
            ->setAfternoonPresent(false)
            ->setAfternoonStart(null)
            ->setAfternoonEnd(null)
            ->setHourlyRate(0);

        $this->calculateTask($attendance);

        if (!$attendance->getAttendanceDate() instanceof \DateTimeImmutable) {
            throw new \DomainException('Date de pointage invalide.');
        }

        $attendance->setCreatedBy($actor);
        $this->entityManager->persist($attendance);
        $this->entityManager->flush();

        return $attendance;
    }

    /** @return array{items: list<InterimAttendance>, totals: array{hours: float, amount: float, count: int, taskKg: float}, month: string} */
    public function workerMonth(InterimWorker $worker, ?string $month = null): array
    {
        $monthDate = $this->monthFromString($month);
        $items = $this->attendanceRepository->findForWorkerMonth($worker, $monthDate);
        $hours = 0.0;
        $amount = 0.0;
        $taskKg = 0.0;
        foreach ($items as $item) {
            $hours += $item->getTotalHoursValue();
            $amount += $item->getTotalAmountValue();
            if ($item->getMode() === InterimAttendance::MODE_TASK) {
                $taskKg += $item->getTaskWeightKgValue();
            }
        }

        return [
            'items' => $items,
            'totals' => [
                'hours' => $hours,
                'amount' => $amount,
                'count' => count($items),
                'taskKg' => $taskKg,
            ],
            'month' => $monthDate->format('Y-m'),
        ];
    }

    /** @return array{filters: array<string, string>, worker: InterimWorker, dateFrom: \DateTimeImmutable, dateTo: \DateTimeImmutable, periodLabel: string, items: list<InterimAttendance>} */
    public function journalDateCorrection(InterimWorker $worker, array $filters = []): array
    {
        $filters = $this->normalizeJournalFilters($filters);
        $dateFrom = new \DateTimeImmutable($filters['dateFrom']);
        $dateTo = new \DateTimeImmutable($filters['dateTo']);

        if ($dateTo < $dateFrom) {
            [$dateFrom, $dateTo] = [$dateTo, $dateFrom];
            $filters['dateFrom'] = $dateFrom->format('Y-m-d');
            $filters['dateTo'] = $dateTo->format('Y-m-d');
        }

        return [
            'filters' => $filters,
            'worker' => $worker,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'periodLabel' => $dateFrom->format('d/m/Y') === $dateTo->format('d/m/Y')
                ? $dateFrom->format('d/m/Y')
                : sprintf('%s au %s', $dateFrom->format('d/m/Y'), $dateTo->format('d/m/Y')),
            'items' => $this->attendanceRepository->findForWorkerPeriod($worker, $dateFrom, $dateTo),
        ];
    }

    public function updateAttendanceDate(InterimAttendance $attendance, \DateTimeImmutable $newDate, User $actor): void
    {
        $today = new \DateTimeImmutable('today');
        if ($newDate > $today) {
            throw new \DomainException('La date de pointage ne peut pas etre dans le futur.');
        }

        $worker = $attendance->getWorker();
        if (!$worker instanceof InterimWorker) {
            throw new \DomainException('Interimaire introuvable pour ce pointage.');
        }

        if ($attendance->getMode() === InterimAttendance::MODE_HOURLY) {
            $existing = $this->attendanceRepository->findHourlyForWorkerAndDate($worker, $newDate);
            if ($existing instanceof InterimAttendance && $existing->getId() !== $attendance->getId()) {
                throw new \DomainException('Ce jour contient deja un pointage horaire pour cet interimaire.');
            }
        }

        $attendance
            ->setAttendanceDate($newDate)
            ->setUpdatedBy($actor);
        $this->entityManager->flush();
    }

    /** @return array{date: \DateTimeImmutable, items: list<InterimAttendance>, rates: array<string, float>, cleaning: array<string, int|float>, boxing: array<string, int|float>, hourly: array<string, int|float>, totals: array<string, int|float>} */
    public function workerDaySummary(InterimWorker $worker, ?\DateTimeImmutable $date = null): array
    {
        $date ??= new \DateTimeImmutable('today');
        $items = $this->attendanceRepository->findForWorkerDate($worker, $date);
        $summary = [
            'date' => $date,
            'items' => $items,
            'rates' => [
                'cleaning' => $this->defaultTaskRate(InterimAttendanceRate::CODE_TASK_CLEANING),
                'boxing' => $this->defaultTaskRate(InterimAttendanceRate::CODE_TASK_BOXING),
            ],
            'cleaning' => [
                'lines' => 0,
                'caisses' => 0.0,
                'kg' => 0.0,
                'amount' => 0.0,
            ],
            'boxing' => [
                'lines' => 0,
                'kg' => 0.0,
                'amount' => 0.0,
            ],
            'hourly' => [
                'lines' => 0,
                'hours' => 0.0,
                'amount' => 0.0,
            ],
            'totals' => [
                'lines' => 0,
                'taskKg' => 0.0,
                'hours' => 0.0,
                'amount' => 0.0,
            ],
        ];

        foreach ($items as $item) {
            $amount = $item->getTotalAmountValue();
            $summary['totals']['lines']++;
            $summary['totals']['amount'] += $amount;

            if ($item->getMode() !== InterimAttendance::MODE_TASK) {
                $summary['hourly']['lines']++;
                $summary['hourly']['hours'] += $item->getTotalHoursValue();
                $summary['hourly']['amount'] += $amount;
                $summary['totals']['hours'] += $item->getTotalHoursValue();

                continue;
            }

            if ($item->getTaskType() === InterimAttendance::TASK_CLEANING_ANCHOVY) {
                $summary['cleaning']['lines']++;
                $summary['cleaning']['caisses'] += $item->getTaskQuantityValue();
                $summary['cleaning']['kg'] += $item->getTaskWeightKgValue();
                $summary['cleaning']['amount'] += $amount;
            } elseif ($item->getTaskType() === InterimAttendance::TASK_BOXING_FILETS) {
                $summary['boxing']['lines']++;
                $summary['boxing']['kg'] += $item->getTaskWeightKgValue();
                $summary['boxing']['amount'] += $amount;
            }

            $summary['totals']['taskKg'] += $item->getTaskWeightKgValue();
        }

        return $summary;
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

    public function defaultTaskRate(string $code): float
    {
        $this->ensureDefaultRates();
        $rate = $this->rateRepository->findOneBy(['code' => $code]);

        return $rate instanceof InterimAttendanceRate && $rate->isActive() ? $rate->getAmountValue() : match ($code) {
            InterimAttendanceRate::CODE_TASK_CLEANING => self::DEFAULT_CLEANING_RATE,
            InterimAttendanceRate::CODE_TASK_BOXING => self::DEFAULT_BOXING_RATE,
            default => 0.0,
        };
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

    private function calculateTask(InterimAttendance $attendance): void
    {
        $quantity = $attendance->getTaskQuantityValue();
        if ($quantity <= 0) {
            throw new \DomainException('Renseignez une quantite superieure a zero pour la tache.');
        }

        if ($attendance->getTaskType() === InterimAttendance::TASK_CLEANING_ANCHOVY) {
            $rate = $this->defaultTaskRate(InterimAttendanceRate::CODE_TASK_CLEANING);
            $weightKg = $quantity * InterimAttendance::CLEANING_BOX_WEIGHT_KG;
            $amount = ($weightKg / InterimAttendance::CLEANING_RATE_WEIGHT_KG) * $rate;
            $attendance
                ->setTaskUnit('30 kg')
                ->setTaskUnitPrice($rate)
                ->setTotalHours(0)
                ->setTotalAmount(round($amount, 2));

            return;
        }

        if ($attendance->getTaskType() === InterimAttendance::TASK_BOXING_FILETS) {
            $rate = $this->defaultTaskRate(InterimAttendanceRate::CODE_TASK_BOXING);
            $attendance
                ->setTaskUnit('kg')
                ->setTaskUnitPrice($rate)
                ->setTotalHours(0)
                ->setTotalAmount(round($quantity * $rate, 2));

            return;
        }

        throw new \DomainException('Selectionnez un type de tache valide.');
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
            InterimAttendanceRate::CODE_TASK_CLEANING => ['Nettoyage anchois', InterimAttendance::MODE_TASK, '30 kg', (string) self::DEFAULT_CLEANING_RATE],
            InterimAttendanceRate::CODE_TASK_BOXING => ['Mise en caisse filets', InterimAttendance::MODE_TASK, 'kg', (string) self::DEFAULT_BOXING_RATE],
        ];

        $changed = false;
        foreach ($defaults as $code => [$label, $mode, $unit, $amount]) {
            $existing = $this->rateRepository->findOneBy(['code' => $code]);
            if ($existing instanceof InterimAttendanceRate) {
                if ($existing->getLabel() !== $label) {
                    $existing->setLabel($label);
                    $changed = true;
                }
                if ($existing->getMode() !== $mode) {
                    $existing->setMode($mode);
                    $changed = true;
                }
                if ($existing->getUnitLabel() !== $unit) {
                    $existing->setUnitLabel($unit);
                    $changed = true;
                }
                if ($existing->getAmountValue() <= 0.0 && (float) $amount > 0.0) {
                    $existing->setAmount($amount);
                    $changed = true;
                }

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
            $changed = true;
        }

        if ($changed) {
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

    /** @return array<string, string> */
    private function normalizeJournalFilters(array $filters): array
    {
        $today = (new \DateTimeImmutable('today'))->format('Y-m-d');
        $normalized = [
            'dateFrom' => trim((string) ($filters['dateFrom'] ?? $today)),
            'dateTo' => trim((string) ($filters['dateTo'] ?? $today)),
        ];

        foreach (['dateFrom', 'dateTo'] as $key) {
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $normalized[$key])) {
                $normalized[$key] = $today;
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
