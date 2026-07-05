<?php

namespace App\Service\CoutRevient;

use App\Entity\CoutRevient;
use App\Entity\User;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class CoutRevientExcelExporterService
{
    public function __construct(
        #[Autowire('%kernel.project_dir%/public')]
        private string $publicDir,
    ) {
    }

    /**
     * @param list<CoutRevient> $items
     * @param array<string, mixed> $stats
     * @param array<string, mixed> $filters
     */
    public function exportGlobal(array $items, array $stats, array $filters, User $actor): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Rapport');
        $this->addLogo($sheet);

        $sheet->mergeCells('A1:T1');
        $sheet->setCellValue('A1', 'Rapport coût de revient');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(18);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->mergeCells('A2:T2');
        $sheet->setCellValue('A2', $this->periodLabel($filters));
        $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->mergeCells('A3:T3');
        $sheet->setCellValue('A3', sprintf('Export le %s par %s', (new \DateTimeImmutable())->format('d/m/Y H:i'), $actor->getDisplayName()));
        $sheet->getStyle('A3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $summary = [
            ['Nombre de lots', (int) ($stats['lots'] ?? 0), 'Poids brut total', (float) ($stats['poids_brut_total'] ?? 0)],
            ['Poids fini total', (float) ($stats['poids_fini_total'] ?? 0), 'Rendement moyen', (float) ($stats['rendement_moyen'] ?? 0)],
            ['Cout total', (float) ($stats['cout_total'] ?? 0), 'Cout moyen/kg', (float) ($stats['cout_moyen_kg'] ?? 0)],
            ['Marge totale', (float) ($stats['marge_totale'] ?? 0), 'Taux marge moyen', (float) ($stats['taux_marge_moyen'] ?? 0)],
        ];
        $sheet->fromArray($summary, null, 'A5');
        $sheet->getStyle('A5:D8')->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $sheet->getStyle('A5:A8')->getFont()->setBold(true);
        $sheet->getStyle('C5:C8')->getFont()->setBold(true);
        $sheet->getStyle('B6:B8')->getNumberFormat()->setFormatCode('#,##0.00');
        $sheet->getStyle('D5:D8')->getNumberFormat()->setFormatCode('#,##0.00');

        $headerRow = 11;
        $headers = [
            'Date',
            'Lot',
            'Produit',
            'Client',
            'Poids brut',
            'Poids production',
            'Poids fini',
            'Dechets',
            'Pertes',
            'Rendement %',
            'Cout matiere',
            'Main oeuvre',
            'Emballage',
            'Charges',
            'Cout total',
            'Cout/kg',
            'Prix vente/kg',
            'Marge/kg',
            'Marge totale',
            'Statut',
        ];
        $sheet->fromArray($headers, null, 'A'.$headerRow);
        $this->styleTableHeader($sheet->getStyle('A'.$headerRow.':T'.$headerRow));

        $row = $headerRow + 1;
        foreach ($items as $item) {
            $sheet->fromArray($this->lotRow($item), null, 'A'.$row);
            ++$row;
        }

        if ($row > $headerRow + 1) {
            $totalRow = $row;
            $sheet->setCellValue('A'.$totalRow, 'TOTAL');
            $sheet->mergeCells('A'.$totalRow.':D'.$totalRow);
            foreach (['E', 'F', 'G', 'H', 'I', 'K', 'L', 'M', 'N', 'O', 'S'] as $column) {
                $sheet->setCellValue($column.$totalRow, sprintf('=SUM(%s%d:%s%d)', $column, $headerRow + 1, $column, $row - 1));
            }
            $sheet->getStyle('A'.$totalRow.':T'.$totalRow)->getFont()->setBold(true);
            $sheet->getStyle('A'.$totalRow.':T'.$totalRow)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFEAF2FF');
            ++$row;
        }

        $lastRow = max($headerRow + 1, $row - 1);
        $sheet->getStyle('A'.$headerRow.':T'.$lastRow)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $sheet->getStyle('E'.($headerRow + 1).':I'.$lastRow)->getNumberFormat()->setFormatCode('#,##0.000');
        $sheet->getStyle('J'.($headerRow + 1).':J'.$lastRow)->getNumberFormat()->setFormatCode('#,##0.00');
        $sheet->getStyle('K'.($headerRow + 1).':S'.$lastRow)->getNumberFormat()->setFormatCode('#,##0.00');
        $sheet->setAutoFilter('A'.$headerRow.':T'.$lastRow);
        $sheet->freezePane('A'.($headerRow + 1));
        $this->autosize($spreadsheet);

        return $this->save($spreadsheet, 'rapport-cout-revient');
    }

    public function exportLot(CoutRevient $item, User $actor): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Fiche lot');
        $this->addLogo($sheet);

        $sheet->mergeCells('A1:F1');
        $sheet->setCellValue('A1', 'Fiche coût de revient');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(18);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->mergeCells('A2:F2');
        $sheet->setCellValue('A2', sprintf('Lot %s - %s', $item->getNumeroLot(), $item->getProduit()));
        $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->mergeCells('A3:F3');
        $sheet->setCellValue('A3', sprintf('Export le %s par %s', (new \DateTimeImmutable())->format('d/m/Y H:i'), $actor->getDisplayName()));
        $sheet->getStyle('A3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sections = [
            ['Informations generales', '', '', '', '', ''],
            ['Date production', $item->getDateProduction()?->format('d/m/Y'), 'Lot', $item->getNumeroLot(), 'Statut', $item->getStatutLabel()],
            ['Produit', $item->getProduit(), 'Espece', $item->getEspecePoisson(), 'Client', $item->getClient()],
            ['Responsable', $item->getResponsableProduction(), '', '', '', ''],
            [],
            ['Resume financier', '', '', '', '', ''],
            ['Cout total', (float) $item->getCoutTotalProduction(), 'Cout/kg', (float) $item->getCoutRevientKg(), 'Rendement %', (float) $item->getRendementPourcentage()],
            ['Prix vente/kg', $item->getPrixVenteKg() !== null ? (float) $item->getPrixVenteKg() : null, 'Marge/kg', (float) $item->getMargeKg(), 'Marge totale', (float) $item->getMargeTotale()],
            ['Taux marge %', (float) $item->getTauxMargePourcentage(), 'Rentabilite', $item->getRentabiliteLabel(), '', ''],
            [],
            ['Detail couts', '', '', '', '', ''],
            ['Matière première', (float) $item->getCoutMatierePremiere(), 'Main oeuvre', (float) $item->getCoutMainOeuvre(), 'Emballage', (float) $item->getCoutEmballageTotal()],
            ['Charges', (float) $item->getCoutChargesTotal(), '', '', '', ''],
            [],
            ['Production', '', '', '', '', ''],
            ['Poids brut', (float) $item->getPoidsBrutRecu(), 'Mis en production', (float) $item->getPoidsMisEnProduction(), 'Poids fini', (float) $item->getPoidsProduitFini()],
            ['Dechets', (float) $item->getPoidsDechets(), 'Pertes', (float) $item->getPoidsPerte(), '', ''],
            [],
            ['Observations', $item->getObservation(), '', '', '', ''],
        ];

        $sheet->fromArray($sections, null, 'A5');
        foreach ([5, 10, 15, 19] as $sectionRow) {
            $sheet->mergeCells('A'.$sectionRow.':F'.$sectionRow);
            $this->styleSectionHeader($sheet->getStyle('A'.$sectionRow.':F'.$sectionRow));
        }

        $lastRow = 23;
        if ($item->getChargeLines()->count() > 0) {
            $lastRow += 2;
            $sheet->mergeCells('A'.$lastRow.':F'.$lastRow);
            $sheet->setCellValue('A'.$lastRow, 'Charges appliquees');
            $this->styleSectionHeader($sheet->getStyle('A'.$lastRow.':F'.$lastRow));
            ++$lastRow;
            $sheet->fromArray(['Charge', 'Catégorie', 'Calcul', 'Quantite', 'Cout unitaire', 'Total'], null, 'A'.$lastRow);
            $sheet->getStyle('A'.$lastRow.':F'.$lastRow)->getFont()->setBold(true);

            foreach ($item->getChargeLines() as $line) {
                ++$lastRow;
                $sheet->fromArray([
                    $line->getName(),
                    $line->getCategoryLabel(),
                    $line->getCalculationUnitLabel(),
                    (float) $line->getQuantity(),
                    (float) $line->getUnitCost(),
                    (float) $line->getTotalAmount(),
                ], null, 'A'.$lastRow);
            }
        }

        $sheet->getStyle('A5:F'.$lastRow)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $sheet->getStyle('B6:F'.$lastRow)->getNumberFormat()->setFormatCode('#,##0.00');
        $sheet->getStyle('A:F')->getAlignment()->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
        $this->autosize($spreadsheet);

        return $this->save($spreadsheet, 'fiche-cout-revient-'.$this->slug((string) $item->getNumeroLot()));
    }

    /** @return list<mixed> */
    private function lotRow(CoutRevient $item): array
    {
        return [
            $item->getDateProduction()?->format('d/m/Y'),
            $item->getNumeroLot(),
            $item->getProduit(),
            $item->getClient(),
            (float) $item->getPoidsBrutRecu(),
            (float) $item->getPoidsMisEnProduction(),
            (float) $item->getPoidsProduitFini(),
            (float) $item->getPoidsDechets(),
            (float) $item->getPoidsPerte(),
            (float) $item->getRendementPourcentage(),
            (float) $item->getCoutMatierePremiere(),
            (float) $item->getCoutMainOeuvre(),
            (float) $item->getCoutEmballageTotal(),
            (float) $item->getCoutChargesTotal(),
            (float) $item->getCoutTotalProduction(),
            (float) $item->getCoutRevientKg(),
            $item->getPrixVenteKg() !== null ? (float) $item->getPrixVenteKg() : null,
            (float) $item->getMargeKg(),
            (float) $item->getMargeTotale(),
            $item->getStatutLabel(),
        ];
    }

    private function addLogo(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet): void
    {
        $logo = $this->findLogo();
        if ($logo === null) {
            return;
        }

        $drawing = new Drawing();
        $drawing->setName('Afriocean Fish');
        $drawing->setPath($logo);
        $drawing->setHeight(58);
        $drawing->setCoordinates('A1');
        $drawing->setWorksheet($sheet);
    }

    private function findLogo(): ?string
    {
        $candidates = [
            $this->publicDir.'/images/logo.png',
            $this->publicDir.'/images/logo.jpg',
            $this->publicDir.'/uploads/logo.png',
            $this->publicDir.'/uploads/logo.jpg',
            $this->publicDir.'/assets/logo.png',
            $this->publicDir.'/assets/logo.jpg',
        ];

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private function styleTableHeader(\PhpOffice\PhpSpreadsheet\Style\Style $style): void
    {
        $style->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
        $style->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF0D6EFD');
        $style->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    }

    private function styleSectionHeader(\PhpOffice\PhpSpreadsheet\Style\Style $style): void
    {
        $style->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
        $style->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF334155');
    }

    private function autosize(Spreadsheet $spreadsheet): void
    {
        foreach ($spreadsheet->getWorksheetIterator() as $sheet) {
            foreach (range('A', $sheet->getHighestColumn()) as $column) {
                $sheet->getColumnDimension($column)->setAutoSize(true);
            }
        }
    }

    private function save(Spreadsheet $spreadsheet, string $prefix): string
    {
        $temp = tempnam(sys_get_temp_dir(), 'afriocean-cout-');
        if ($temp === false) {
            throw new \RuntimeException('Impossible de preparer le fichier Excel.');
        }

        $path = $temp.'.xlsx';
        @unlink($temp);
        (new Xlsx($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();

        return $path;
    }

    /** @param array<string, mixed> $filters */
    private function periodLabel(array $filters): string
    {
        $from = trim((string) ($filters['dateFrom'] ?? ''));
        $to = trim((string) ($filters['dateTo'] ?? ''));
        if ($from !== '' || $to !== '') {
            return sprintf('Periode : %s au %s', $from !== '' ? $from : 'debut', $to !== '' ? $to : 'aujourd hui');
        }

        return 'Toutes les periodes';
    }

    private function slug(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/i', '-', $value) ?: 'lot';

        return trim($value, '-') ?: 'lot';
    }
}
