<?php

namespace App\Tests\Entity;

use App\Entity\InventoryCartonStock;
use App\Entity\InventoryCartonStockLine;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class InventoryCartonStockLineTest extends TestCase
{
    #[Test]
    public function itNormalizesStockLineValues(): void
    {
        $stock = (new InventoryCartonStock())->setName('Carton test');
        $line = (new InventoryCartonStockLine())
            ->setStock($stock)
            ->setGroupName(' Client ')
            ->setReference(' TROCIADO ')
            ->setQuantity(2400)
            ->setUnitPrice('8,4')
            ->setTotalAmount(20160)
            ->setLineType('item');

        self::assertSame($stock, $line->getStock());
        self::assertSame('Client', $line->getGroupName());
        self::assertSame('TROCIADO', $line->getReference());
        self::assertSame(2400, $line->getQuantity());
        self::assertSame('8.400', $line->getUnitPrice());
        self::assertSame('20160.000', $line->getTotalAmount());
        self::assertSame('8,4', $line->getUnitPriceLabel());
        self::assertSame('20 160', $line->getTotalAmountLabel());
        self::assertSame('Ligne stock', $line->getLineTypeLabel());
    }

    #[Test]
    public function itFormatsAndParsesFrenchDecimalValues(): void
    {
        $line = (new InventoryCartonStockLine())
            ->setUnitPrice('8,400')
            ->setTotalAmount('33 575,770');

        self::assertSame('8.400', $line->getUnitPrice());
        self::assertSame('33575.770', $line->getTotalAmount());
        self::assertSame('8,4', $line->getUnitPriceLabel());
        self::assertSame('33 575,77', $line->getTotalAmountLabel());
    }

    #[Test]
    public function itRecognizesSummaryAndTransportLines(): void
    {
        $line = new InventoryCartonStockLine();

        $line->setLineType('summary');
        self::assertTrue($line->isSummary());
        self::assertFalse($line->isTransport());

        $line->setLineType('transport');
        self::assertFalse($line->isSummary());
        self::assertTrue($line->isTransport());
    }
}
