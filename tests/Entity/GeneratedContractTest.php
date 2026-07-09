<?php

namespace App\Tests\Entity;

use App\Entity\GeneratedContract;
use PHPUnit\Framework\TestCase;

final class GeneratedContractTest extends TestCase
{
    public function testConditioningContractUsesOperationalDefaults(): void
    {
        $contract = new GeneratedContract();
        $year = (int) (new \DateTimeImmutable('today'))->format('Y');

        self::assertSame(GeneratedContract::TYPE_CONDITIONING, $contract->getContractType());
        self::assertSame(sprintf('%d/%d', $year, $year + 1), $contract->getCampaign());
        self::assertSame('Casablanca', $contract->getSigningCity());
        self::assertSame(GeneratedContract::STATUS_DRAFT, $contract->getStatus());
    }

    public function testGeneratedStatusHasReadablePresentation(): void
    {
        $contract = (new GeneratedContract())->setStatus(GeneratedContract::STATUS_GENERATED);

        self::assertSame('PDF genere', $contract->getStatusLabel());
        self::assertSame('text-bg-success', $contract->getStatusBadgeClass());
    }
}
