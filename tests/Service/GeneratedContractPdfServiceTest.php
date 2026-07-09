<?php

namespace App\Tests\Service;

use App\Entity\GeneratedContract;
use App\Service\GeneratedContractPdfService;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class GeneratedContractPdfServiceTest extends KernelTestCase
{
    public function testServiceGeneratesARealPdfFromContractData(): void
    {
        self::bootKernel();
        $service = self::getContainer()->get(GeneratedContractPdfService::class);
        \assert($service instanceof GeneratedContractPdfService);

        $contract = (new GeneratedContract())
            ->setReference('CTR-COND-2026-TEST')
            ->setContractDate(new \DateTimeImmutable('2026-07-09'))
            ->setCampaign('2026/2027')
            ->setClientCompanyName('STE NETTOFISH')
            ->setClientAddress('LOT EL WAKALA 01 BLOC D N 705, LAAYOUNE, MAROC')
            ->setRepresentativeTitle('Monsieur')
            ->setRepresentativeName('LAZRAK MOHAMED FAHID')
            ->setRepresentativeIdNumber('BE701435')
            ->setSigningCity('Casablanca');

        $pdf = $service->generate($contract);

        self::assertStringStartsWith('%PDF-', $pdf);
        self::assertGreaterThan(10000, strlen($pdf));
    }
}
