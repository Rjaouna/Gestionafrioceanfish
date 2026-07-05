<?php

namespace App\Service\FishReception;

use App\Entity\FishReception;
use App\Entity\User;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class FishReceptionExcelFormService
{
    private const STAGES = ['reception', 'traitement', 'emballage', 'congelation', 'stockage', 'expedition'];

    public function __construct(
        #[Autowire('%kernel.project_dir%/public')]
        private string $publicDir,
    ) {
    }

    /** @param array<string, list<string>> $choices */
    public function exportTemplate(string $stage, ?FishReception $reception, User $actor, array $choices = []): string
    {
        $this->assertStage($stage);
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle($this->stageTitle($stage));

        $this->addHeader($sheet, $stage, $reception, $actor);

        $headerRow = 6;
        $sheet->fromArray(['Champ', 'Valeur a remplir', 'Obligatoire', 'Type', 'Aide', 'Cle technique'], null, 'A'.$headerRow);
        $this->styleTableHeader($sheet->getStyle('A'.$headerRow.':F'.$headerRow));

        $row = $headerRow + 1;
        foreach ($this->fields($stage) as $field) {
            $value = $this->defaultValue($field['name'], $field['type'], $reception, $stage);
            $sheet->setCellValue('A'.$row, $field['label']);
            $sheet->setCellValue('B'.$row, $value);
            $sheet->setCellValue('C'.$row, $field['required'] ? 'Oui' : 'Non');
            $sheet->setCellValue('D'.$row, $this->typeLabel($field['type']));
            $sheet->setCellValue('E'.$row, $this->helpText($field, $choices));
            $sheet->setCellValueExplicit('F'.$row, $field['name'], DataType::TYPE_STRING);
            $this->styleValueCell($sheet, $row, $field['type']);
            ++$row;
        }

        $lastRow = $row - 1;
        $sheet->getStyle('A'.$headerRow.':F'.$lastRow)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $sheet->getStyle('A'.($headerRow + 1).':F'.$lastRow)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
        $sheet->getStyle('C'.($headerRow + 1).':C'.$lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getColumnDimension('F')->setVisible(false);
        $sheet->freezePane('A7');
        $sheet->setAutoFilter('A'.$headerRow.':E'.$lastRow);

        $this->addInstructionsSheet($spreadsheet);
        $spreadsheet->setActiveSheetIndex(0);
        $this->autosize($spreadsheet);

        return $this->save($spreadsheet, 'modele-'.$stage.'-reception');
    }

    /**
     * @param array<string, list<string>> $choices
     *
     * @return array<string, mixed>
     */
    public function importTemplate(string $stage, string $path, array $choices = []): array
    {
        $this->assertStage($stage);
        $spreadsheet = IOFactory::load($path);
        $sheet = $spreadsheet->getActiveSheet();
        $fields = [];
        foreach ($this->fields($stage) as $field) {
            $fields[$field['name']] = $field;
        }

        $values = [];
        $errors = [];
        $rows = [];
        $highestRow = $sheet->getHighestDataRow();

        for ($row = 7; $row <= $highestRow; ++$row) {
            $key = trim((string) $sheet->getCell('F'.$row)->getValue());
            if ($key === '' || !isset($fields[$key])) {
                continue;
            }

            $field = $fields[$key];
            $rawValue = $sheet->getCell('B'.$row)->getValue();
            [$value, $error] = $this->normalizeImportedValue($rawValue, $field, $sheet, $row, $choices);
            if ($error !== null) {
                $errors[$key][] = $error;
            } elseif ($value !== null && $value !== '') {
                $values[$key] = $value;
            }

            $rows[] = [
                'row' => $row,
                'field' => $key,
                'label' => $field['label'],
                'value' => $value ?? (string) $rawValue,
                'status' => $error === null ? 'ok' : 'error',
                'message' => $error,
            ];
        }

        $spreadsheet->disconnectWorksheets();

        if ($rows === []) {
            return [
                'values' => [],
                'errors' => ['_file' => ['Le fichier ne correspond pas au modele attendu. Telechargez un nouveau modele puis reessayez.']],
                'rows' => [],
                'hasErrors' => true,
            ];
        }

        return [
            'values' => $values,
            'errors' => $errors,
            'rows' => $rows,
            'hasErrors' => $errors !== [],
        ];
    }

    /** @return list<array{name: string, label: string, type: string, required: bool, help?: string, choices?: string}> */
    public function fields(string $stage): array
    {
        $this->assertStage($stage);

        return match ($stage) {
            'traitement' => [
                $this->field('quantity', 'Quantite a envoyer au traitement (kg)', 'number', true, 'Saisir la quantite en kg.'),
                $this->field('dateDebutTraitement', 'Date debut traitement', 'date', true),
                $this->field('heureDebutTraitement', 'Heure debut traitement', 'time', false),
                $this->field('temperatureEauGlacee', 'Temperature eau glacee', 'number', false, 'Valeur negative autorisee.'),
                $this->field('poidsMoyenParCaisse', 'Poids moyen par caisse (kg)', 'number', false),
                $this->field('nombreMoules', 'Nombre de moules', 'integer', false),
                $this->field('nombreCaissesParEtage', 'Nombre de caisses par etage', 'integer', false, 'Par defaut : 5.'),
                $this->field('nombreNiveauxPalette', 'Nombre de niveaux', 'integer', false, 'Par defaut : 16.'),
            ],
            'emballage' => [
                $this->field('quantity', 'Quantite a conditionner / emballer (kg)', 'number', true),
                $this->field('dateConditionnement', 'Date conditionnement', 'date', false),
                $this->field('heureDebutConditionnement', 'Heure debut conditionnement', 'time', false),
                $this->field('heureFinConditionnement', 'Heure fin conditionnement', 'time', false),
                $this->field('poidsNet', 'Poids net (kg)', 'number', false),
                $this->field('produitConditionne', 'Produit conditionne', 'text', true, null, 'produitConditionne'),
            ],
            'congelation' => [
                $this->field('quantity', 'Quantite a congeler (kg)', 'number', true),
                $this->field('tunnel', 'Tunnel', 'text', true, null, 'tunnel'),
                $this->field('heureEntreeTunnel', 'Heure entree tunnel', 'time', false),
                $this->field('temperatureTunnel', 'Temperature tunnel', 'number', false, 'Valeur negative autorisee.'),
                $this->field('dateSortieTunnel', 'Date sortie tunnel', 'date', false),
                $this->field('temperatureCoeurProduit', 'Temperature a coeur produit', 'number', false, 'Valeur negative autorisee.'),
            ],
            'stockage' => [
                $this->field('quantity', 'Quantite a entrer en stock (kg)', 'number', true),
                $this->field('chambreFroide', 'Chambre froide / zone de stockage', 'text', true, null, 'chambreFroide'),
                $this->field('temperatureChambre', 'Temperature chambre', 'number', false, 'Valeur negative autorisee.'),
                $this->field('temperatureStockage', 'Temperature produit stocke', 'number', false, 'Valeur negative autorisee.'),
                $this->field('dateEntreeStockage', 'Date entree stockage', 'date', false),
                $this->field('heureEntreeStockage', 'Heure entree stockage', 'time', false),
            ],
            'expedition' => [
                $this->field('quantity', 'Quantite a expedier (kg)', 'number', true),
                $this->field('expeditionDateDepart', 'Date expedition', 'date', true),
                $this->field('expeditionHeureDepart', 'Heure depart camion', 'time', true),
                $this->field('destinationFinaleClient', 'Destination finale / Client', 'text', true, null, 'destinationFinaleClient'),
                $this->field('expeditionMatriculeVehicule', 'Matricule camion', 'text', true),
                $this->field('expeditionChauffeur', 'Nom chauffeur', 'text', true),
                $this->field('expeditionResponsableChargement', 'Responsable chargement', 'text', true),
                $this->field('expeditionTemperatureProduit', 'Temperature produit au chargement', 'number', false, 'Valeur negative autorisee.'),
                $this->field('expeditionNumeroPlomb', 'Numero plomb / scelle', 'text', false),
                $this->field('expeditionObservations', 'Observations expedition', 'text', false),
            ],
            default => [
                $this->field('dateReception', 'Date de reception', 'date', true),
                $this->field('heureDebutReception', 'Heure debut reception', 'time', false),
                $this->field('heureFinReception', 'Heure fin reception', 'time', false),
                $this->field('fournisseur', 'Fournisseur', 'text', true, null, 'fournisseur'),
                $this->field('provenance', 'Provenance', 'text', false, null, 'provenance'),
                $this->field('numeroBonLivraison', 'N Bon de livraison', 'text', false),
                $this->field('matriculeVehicule', 'Matricule vehicule', 'text', false),
                $this->field('chauffeur', 'Chauffeur', 'text', false),
                $this->field('especePoisson', 'Espece poisson', 'text', true, null, 'especePoisson'),
                $this->field('nomScientifique', 'Nom scientifique', 'text', false),
                $this->field('presentationProduit', 'Presentation produit', 'text', true, null, 'presentationProduit'),
                $this->field('etatProduit', 'Etat produit', 'text', true, null, 'etatProduit'),
                $this->field('quantiteIndiqueeBl', 'Quantite indiquee sur BL (kg)', 'number', false),
                $this->field('quantiteReceptionnee', 'Quantite receptionnee (kg)', 'number', true),
                $this->field('nombreCaissesReception', 'Nombre de caisses reception', 'integer', false),
                $this->field('temperaturePoissonReception', 'Temperature poisson reception', 'number', false, 'Valeur negative autorisee.'),
                $this->field('categorieFraicheur', 'Categorie fraicheur', 'text', true, null, 'categorieFraicheur'),
                $this->field('presenceGlace', 'Presence de glace', 'bool', false, 'Oui ou Non.'),
                $this->field('responsableProduction', 'Responsable production', 'text', false),
                $this->field('signatureResponsable', 'Signature', 'text', false),
                $this->field('observations', 'Observations', 'text', false),
            ],
        };
    }

    /** @return array{name: string, label: string, type: string, required: bool, help?: string, choices?: string} */
    private function field(string $name, string $label, string $type, bool $required, ?string $help = null, ?string $choices = null): array
    {
        $field = compact('name', 'label', 'type', 'required');
        if ($help !== null) {
            $field['help'] = $help;
        }
        if ($choices !== null) {
            $field['choices'] = $choices;
        }

        return $field;
    }

    private function addHeader(Worksheet $sheet, string $stage, ?FishReception $reception, User $actor): void
    {
        $this->addLogo($sheet);
        $sheet->mergeCells('A1:F1');
        $sheet->setCellValue('A1', 'AFRIOCEAN FISH');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(22)->getColor()->setARGB('FFFFFFFF');
        $sheet->getStyle('A1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF142D63');
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->mergeCells('A2:F2');
        $sheet->setCellValue('A2', 'Formulaire '.$this->stageTitle($stage));
        $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(15)->getColor()->setARGB('FF142D63');
        $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->mergeCells('A3:F3');
        $sheet->setCellValue('A3', $reception instanceof FishReception ? sprintf('%s - %s - %s', $reception->getNumeroReception(), $reception->getFournisseur(), $reception->getEspecePoisson()) : 'Nouvelle reception');
        $sheet->getStyle('A3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->mergeCells('A4:F4');
        $sheet->setCellValue('A4', sprintf('Modele genere le %s par %s', (new \DateTimeImmutable())->format('d/m/Y H:i'), $actor->getDisplayName()));
        $sheet->getStyle('A4')->getFont()->getColor()->setARGB('FF64748B');
        $sheet->getStyle('A4')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    }

    private function addInstructionsSheet(Spreadsheet $spreadsheet): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Instructions');
        $sheet->mergeCells('A1:D1');
        $sheet->setCellValue('A1', 'Instructions de remplissage');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16)->getColor()->setARGB('FFFFFFFF');
        $sheet->getStyle('A1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF142D63');
        $sheet->fromArray([
            ['1', 'Remplir uniquement la colonne "Valeur a remplir".'],
            ['2', 'Ne pas supprimer les lignes et ne pas modifier la colonne technique masquee.'],
            ['3', 'Les dates doivent etre au format jj/mm/aaaa ou aaaa-mm-jj.'],
            ['4', 'Les heures doivent etre au format hh:mm.'],
            ['5', 'Importer le fichier dans l application, corriger les champs rouges, puis valider.'],
        ], null, 'A3');
        $sheet->getStyle('A3:D7')->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $sheet->getStyle('A3:A7')->getFont()->setBold(true);
    }

    /** @param array{name: string, label: string, type: string, required: bool, help?: string, choices?: string} $field */
    private function helpText(array $field, array $choices): string
    {
        $help = (string) ($field['help'] ?? '');
        $choiceKey = (string) ($field['choices'] ?? '');
        if ($choiceKey !== '' && !empty($choices[$choiceKey])) {
            $help .= ($help !== '' ? ' ' : '').'Valeurs deja connues : '.implode(', ', array_slice($choices[$choiceKey], 0, 20));
            if (count($choices[$choiceKey]) > 20) {
                $help .= '...';
            }
        }

        return $help;
    }

    private function defaultValue(string $field, string $type, ?FishReception $reception, string $stage): mixed
    {
        if ($field === 'quantity' && $reception instanceof FishReception) {
            return match ($stage) {
                'traitement' => $reception->getQuantiteDisponibleReceptionValue(),
                'emballage' => $reception->getQuantiteDisponibleTraitementValue(),
                'congelation' => $reception->getQuantiteDisponibleEmballageValue(),
                'stockage' => $reception->getQuantiteDisponibleCongelationValue(),
                'expedition' => $reception->getQuantiteDisponibleStockageValue(),
                default => null,
            };
        }

        if ($field === 'nombreCaissesParEtage') {
            return 5;
        }
        if ($field === 'nombreNiveauxPalette') {
            return 16;
        }
        if ($field === 'presenceGlace') {
            return 'Oui';
        }

        if (!$reception instanceof FishReception) {
            return $type === 'date' ? (new \DateTimeImmutable())->format('d/m/Y') : null;
        }

        $getter = 'get'.ucfirst($field);
        if (!method_exists($reception, $getter)) {
            return null;
        }

        $value = $reception->{$getter}();
        if ($value instanceof \DateTimeImmutable) {
            return $type === 'time' ? $value->format('H:i') : $value->format('d/m/Y');
        }
        if (is_bool($value)) {
            return $value ? 'Oui' : 'Non';
        }

        return $value;
    }

    private function styleValueCell(Worksheet $sheet, int $row, string $type): void
    {
        $sheet->getStyle('B'.$row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFF8FAFC');
        $sheet->getStyle('B'.$row)->getFont()->getColor()->setARGB('FF0F172A');
        if (in_array($type, ['number', 'integer'], true)) {
            $sheet->getStyle('B'.$row)->getNumberFormat()->setFormatCode($type === 'integer' ? '#,##0' : '#,##0.000');
        } elseif ($type === 'date') {
            $sheet->getStyle('B'.$row)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_DATE_DDMMYYYY);
        } elseif ($type === 'time') {
            $sheet->getStyle('B'.$row)->getNumberFormat()->setFormatCode('hh:mm');
        }
    }

    /** @param array{name: string, label: string, type: string, required: bool, help?: string, choices?: string} $field */
    private function normalizeImportedValue(mixed $rawValue, array $field, Worksheet $sheet, int $row, array $choices): array
    {
        $value = is_string($rawValue) ? trim($rawValue) : $rawValue;
        if ($value === null || $value === '') {
            return $field['required'] ? [null, 'Champ obligatoire non renseigne.'] : ['', null];
        }

        $type = $field['type'];
        if ($type === 'date') {
            $date = $this->normalizeDate($value, $sheet, $row);

            return $date === null ? [null, 'Date invalide. Format attendu : jj/mm/aaaa.'] : [$date, null];
        }

        if ($type === 'time') {
            $time = $this->normalizeTime($value, $sheet, $row);

            return $time === null ? [null, 'Heure invalide. Format attendu : hh:mm.'] : [$time, null];
        }

        if ($type === 'number' || $type === 'integer') {
            $number = str_replace(',', '.', trim((string) $value));
            if (!is_numeric($number)) {
                return [null, 'Nombre invalide.'];
            }

            $float = (float) $number;
            if ($field['name'] === 'quantity' && $float <= 0) {
                return [null, 'La quantite doit etre superieure a zero.'];
            }

            return [$type === 'integer' ? (string) max(0, (int) round($float)) : (string) $float, null];
        }

        if ($type === 'bool') {
            $normalized = mb_strtolower(trim((string) $value));
            if (in_array($normalized, ['oui', 'o', 'yes', 'true', '1'], true)) {
                return ['1', null];
            }
            if (in_array($normalized, ['non', 'n', 'no', 'false', '0'], true)) {
                return ['0', null];
            }

            return [null, 'Valeur attendue : Oui ou Non.'];
        }

        $text = trim((string) $value);
        if ($text === '' && $field['required']) {
            return [null, 'Champ obligatoire non renseigne.'];
        }

        $choiceKey = (string) ($field['choices'] ?? '');
        if (in_array($field['name'], ['tunnel', 'chambreFroide'], true) && $choiceKey !== '' && !empty($choices[$choiceKey]) && !in_array($text, $choices[$choiceKey], true)) {
            return [null, 'Valeur absente de Composition usine. Choisissez une valeur proposee dans le modele.'];
        }

        return [$text, null];
    }

    private function normalizeDate(mixed $value, Worksheet $sheet, int $row): ?string
    {
        $cell = $sheet->getCell('B'.$row);
        if (is_numeric($value) && ExcelDate::isDateTime($cell)) {
            return ExcelDate::excelToDateTimeObject((float) $value)->format('Y-m-d');
        }

        $raw = trim((string) $value);
        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y'] as $format) {
            $date = \DateTimeImmutable::createFromFormat('!'.$format, $raw);
            if ($date instanceof \DateTimeImmutable) {
                return $date->format('Y-m-d');
            }
        }

        return null;
    }

    private function normalizeTime(mixed $value, Worksheet $sheet, int $row): ?string
    {
        $cell = $sheet->getCell('B'.$row);
        if (is_numeric($value)) {
            if (ExcelDate::isDateTime($cell)) {
                return ExcelDate::excelToDateTimeObject((float) $value)->format('H:i');
            }
            if ((float) $value >= 0.0 && (float) $value < 1.0) {
                $seconds = (int) round((float) $value * 86400);

                return sprintf('%02d:%02d', intdiv($seconds, 3600), intdiv($seconds % 3600, 60));
            }
        }

        $raw = trim((string) $value);
        if (preg_match('/^\d{1,2}:\d{2}(:\d{2})?$/', $raw)) {
            [$hour, $minute] = array_map('intval', explode(':', $raw));
            if ($hour >= 0 && $hour <= 23 && $minute >= 0 && $minute <= 59) {
                return sprintf('%02d:%02d', $hour, $minute);
            }
        }

        return null;
    }

    private function addLogo(Worksheet $sheet): void
    {
        $logo = $this->findLogo();
        if ($logo === null) {
            return;
        }

        $drawing = new \PhpOffice\PhpSpreadsheet\Worksheet\Drawing();
        $drawing->setName('Afriocean Fish');
        $drawing->setPath($logo);
        $drawing->setHeight(42);
        $drawing->setCoordinates('A1');
        $drawing->setWorksheet($sheet);
    }

    private function findLogo(): ?string
    {
        foreach ([
            $this->publicDir.'/images/logo.png',
            $this->publicDir.'/images/logo.jpg',
            $this->publicDir.'/uploads/logo.png',
            $this->publicDir.'/uploads/logo.jpg',
            $this->publicDir.'/assets/logo.png',
            $this->publicDir.'/assets/logo.jpg',
        ] as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private function stageTitle(string $stage): string
    {
        return match ($stage) {
            'traitement' => 'Traitement / Production',
            'emballage' => 'Conditionnement / Emballage',
            'congelation' => 'Congelation',
            'stockage' => 'Stockage',
            'expedition' => 'Expedition',
            default => 'Reception',
        };
    }

    private function typeLabel(string $type): string
    {
        return match ($type) {
            'date' => 'Date',
            'time' => 'Heure',
            'number' => 'Nombre',
            'integer' => 'Nombre entier',
            'bool' => 'Oui / Non',
            default => 'Texte',
        };
    }

    private function styleTableHeader(\PhpOffice\PhpSpreadsheet\Style\Style $style): void
    {
        $style->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
        $style->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF0D6EFD');
        $style->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    }

    private function autosize(Spreadsheet $spreadsheet): void
    {
        foreach ($spreadsheet->getWorksheetIterator() as $sheet) {
            foreach (range('A', $sheet->getHighestColumn()) as $column) {
                $sheet->getColumnDimension($column)->setAutoSize(true);
            }
            $sheet->getColumnDimension('E')->setWidth(52);
        }
    }

    private function save(Spreadsheet $spreadsheet, string $prefix): string
    {
        $temp = tempnam(sys_get_temp_dir(), 'afriocean-reception-');
        if ($temp === false) {
            throw new \RuntimeException('Impossible de preparer le fichier Excel.');
        }

        $path = $temp.'.xlsx';
        @unlink($temp);
        (new Xlsx($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();

        return $path;
    }

    private function assertStage(string $stage): void
    {
        if (!in_array($stage, self::STAGES, true)) {
            throw new \InvalidArgumentException('Phase reception invalide.');
        }
    }
}
