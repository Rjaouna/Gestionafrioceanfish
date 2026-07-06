<?php

namespace App\Tests\Entity;

use App\Entity\FishReception;
use App\Entity\FishReceptionStorageMovement;
use PHPUnit\Framework\TestCase;

final class FishReceptionWorkflowQuantitiesTest extends TestCase
{
    public function testFreezingAvailableQuantityRequiresTreatmentMovement(): void
    {
        $reception = (new FishReception())
            ->setQuantiteReceptionnee(700)
            ->setQuantiteTotalePreparee(700);

        self::assertSame(0.0, $reception->getQuantiteEnTraitementValue());
        self::assertSame(0.0, $reception->getQuantiteDisponibleTraitementValue());
    }

    public function testFreezingAvailableQuantityUsesOnlyQuantityInTreatment(): void
    {
        $reception = (new FishReception())
            ->setQuantiteReceptionnee(1000)
            ->setQuantiteTotalePreparee(700)
            ->setQuantiteCongelee(250);

        $reception->addStorageMovement($this->movement(FishReceptionStorageMovement::TYPE_INITIAL_ENTRY, 1000));
        $reception->addStorageMovement($this->movement(FishReceptionStorageMovement::TYPE_INITIAL_EXIT, -600));

        self::assertSame(600.0, $reception->getQuantiteEnTraitementValue());
        self::assertSame(350.0, $reception->getQuantiteDisponibleTraitementValue());
    }

    private function movement(string $type, float $quantity): FishReceptionStorageMovement
    {
        return (new FishReceptionStorageMovement())
            ->setStorageStage(FishReceptionStorageMovement::STAGE_INITIAL)
            ->setMovementType($type)
            ->setLocation('CHN-0001')
            ->setQuantityKg($quantity);
    }
}
