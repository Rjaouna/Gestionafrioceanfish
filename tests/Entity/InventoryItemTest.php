<?php

namespace App\Tests\Entity;

use App\Entity\InventoryCampaignLine;
use App\Entity\InventoryItem;
use PHPUnit\Framework\TestCase;

final class InventoryItemTest extends TestCase
{
    public function testAvailableQuantityCannotExceedTotalQuantity(): void
    {
        $item = (new InventoryItem())
            ->setReference('INV-TEST-001')
            ->setName('Laptop')
            ->setQuantity(3)
            ->setAvailableQuantity(5);

        self::assertSame(3, $item->getAvailableQuantity());
    }

    public function testReducingQuantityCapsAvailableQuantity(): void
    {
        $item = (new InventoryItem())
            ->setReference('INV-TEST-002')
            ->setName('Screens')
            ->setQuantity(8)
            ->setAvailableQuantity(6)
            ->setQuantity(2);

        self::assertSame(2, $item->getQuantity());
        self::assertSame(2, $item->getAvailableQuantity());
    }

    public function testCampaignLineDetectsQuantityDiscrepancy(): void
    {
        $line = (new InventoryCampaignLine())
            ->setTheoreticalQuantity(4)
            ->setCountedQuantity(3)
            ->setTheoreticalLocation('Depot / A1')
            ->setCountedLocation('Depot / A1');

        self::assertTrue($line->hasDiscrepancy());
    }

    public function testLogisticsStatusProvidesLabelAndColor(): void
    {
        $item = (new InventoryItem())->setLogisticsStatus('transferred_new');

        self::assertSame('Transféré vers la nouvelle usine', $item->getLogisticsStatusLabel());
        self::assertSame('#0d6efd', $item->getLogisticsColor());
    }

    public function testInvalidLogisticsStatusIsRejected(): void
    {
        $this->expectException(\DomainException::class);

        (new InventoryItem())->setLogisticsStatus('unknown');
    }
}
