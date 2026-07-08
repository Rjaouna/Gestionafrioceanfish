<?php

namespace App\Tests\Entity;

use App\Entity\InterimAttendance;
use PHPUnit\Framework\TestCase;

final class InterimAttendanceTaskTest extends TestCase
{
    public function testCleaningTaskConvertsBoxesToWeight(): void
    {
        $attendance = (new InterimAttendance())
            ->setMode(InterimAttendance::MODE_TASK)
            ->setTaskType(InterimAttendance::TASK_CLEANING_ANCHOVY)
            ->setTaskQuantity(3)
            ->setTaskUnit('30 kg')
            ->setTaskUnitPrice(25)
            ->setTotalAmount(25);

        self::assertSame('Nettoyage anchois', $attendance->getPresenceLabel());
        self::assertSame(30.0, $attendance->getTaskWeightKgValue());
        self::assertSame('3 caisses - 30 kg', $attendance->getTimeRangeLabel());
        self::assertSame(25.0, $attendance->getTaskUnitPriceValue());
        self::assertSame(25.0, $attendance->getTotalAmountValue());
    }

    public function testBoxingTaskUsesKilogramsDirectly(): void
    {
        $attendance = (new InterimAttendance())
            ->setMode(InterimAttendance::MODE_TASK)
            ->setTaskType(InterimAttendance::TASK_BOXING_FILETS)
            ->setTaskQuantity(125.5)
            ->setTaskUnit('kg')
            ->setTaskUnitPrice(2)
            ->setTotalAmount(251);

        self::assertSame('Mise en caisse filets', $attendance->getPresenceLabel());
        self::assertSame(125.5, $attendance->getTaskWeightKgValue());
        self::assertSame('125,500 kg', $attendance->getTimeRangeLabel());
        self::assertSame(2.0, $attendance->getTaskUnitPriceValue());
        self::assertSame(251.0, $attendance->getTotalAmountValue());
    }
}
