<?php

namespace App\Tests\Service;

use App\Entity\Document;
use App\Service\DocumentStorageService;
use PHPUnit\Framework\TestCase;

final class DocumentStorageServiceTest extends TestCase
{
    public function testDownloadFileNameUsesDocumentTitleAndOriginalExtension(): void
    {
        $document = (new Document())
            ->setName('Contrat client signé')
            ->setOriginalFileName('scan_2026.pdf');

        self::assertSame('Contrat client signé.pdf', $this->storage()->downloadFileName($document));
    }

    public function testDownloadFileNameDoesNotDuplicateExtensionAndCleansInvalidCharacters(): void
    {
        $document = (new Document())
            ->setName('Rapport: équipe/juin.XLSX')
            ->setOriginalFileName('tableau.xlsx');

        self::assertSame('Rapport- équipe-juin.XLSX', $this->storage()->downloadFileName($document));
    }

    private function storage(): DocumentStorageService
    {
        return new DocumentStorageService(__DIR__, sys_get_temp_dir(), 10485760);
    }
}
