<?php

namespace App\Tests\Entity;

use App\Entity\WasteSale;
use PHPUnit\Framework\TestCase;

final class WasteSaleTest extends TestCase
{
    public function testWasteSaleCalculatesTotalWithDefaultUnitPrice(): void
    {
        $sale = (new WasteSale())->setWeightKg(100);

        self::assertSame(0.60, $sale->unitPriceValue());
        self::assertSame(60.0, $sale->totalAmountValue());
    }

    public function testWasteSaleNormalizesCommaDecimals(): void
    {
        $sale = (new WasteSale())
            ->setWeightKg('12,500')
            ->setUnitPrice('0,60');

        self::assertSame(12.5, $sale->weightKgValue());
        self::assertSame(7.5, $sale->totalAmountValue());
    }
}
