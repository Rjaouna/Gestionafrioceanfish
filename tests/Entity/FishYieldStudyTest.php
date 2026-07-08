<?php

namespace App\Tests\Entity;

use App\Entity\FishYieldStudy;
use PHPUnit\Framework\TestCase;

final class FishYieldStudyTest extends TestCase
{
    public function testStudyCalculatesWaterYieldGapAndContainerProjection(): void
    {
        $study = (new FishYieldStudy())
            ->setRawBoxWeight(100)
            ->setThawedBoxWeight(90)
            ->setFinishedProductWeight(50)
            ->setWasteWeight(35)
            ->setLossWeight(5)
            ->setContainerWeight(10000);

        self::assertSame(10.0, $study->waterWeightValue());
        self::assertSame(10.0, $study->waterRate());
        self::assertSame(90.0, $study->totalWorkedOutputValue());
        self::assertSame(0.0, $study->processGapValue());
        self::assertEqualsWithDelta(55.555, $study->yieldRate(), 0.001);
        self::assertSame(1000.0, $study->containerWaterEstimateValue());
        self::assertSame(9000.0, $study->containerThawedEstimateValue());
        self::assertSame(5000.0, $study->containerFinishedEstimateValue());
        self::assertSame(3500.0, $study->containerWasteEstimateValue());
        self::assertSame(500.0, $study->containerLossEstimateValue());
    }
}
