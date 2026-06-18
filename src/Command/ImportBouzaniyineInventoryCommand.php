<?php

namespace App\Command;

use App\Entity\InventoryCartonStock;
use App\Entity\InventoryCartonStockLine;
use App\Entity\InventoryCategory;
use App\Entity\InventoryItem;
use App\Entity\InventoryLocation;
use App\Entity\InventorySite;
use App\Repository\InventoryCartonStockLineRepository;
use App\Repository\InventoryCartonStockRepository;
use App\Repository\InventoryCategoryRepository;
use App\Repository\InventoryItemRepository;
use App\Repository\InventoryLocationRepository;
use App\Repository\InventorySiteRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:inventory:import-bouzaniyine',
    aliases: ['app:inventory:load-fixtures'],
    description: 'Imports or updates the Bouzaniyine inventory without deleting existing production data.',
)]
final class ImportBouzaniyineInventoryCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly InventoryItemRepository $itemRepository,
        private readonly InventoryCategoryRepository $categoryRepository,
        private readonly InventorySiteRepository $siteRepository,
        private readonly InventoryLocationRepository $locationRepository,
        private readonly InventoryCartonStockRepository $cartonStockRepository,
        private readonly InventoryCartonStockLineRepository $cartonLineRepository,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $data = $this->fixtureData();
        $additionalSites = array_values(array_filter(array_map(
            static fn (mixed $name): string => trim((string) $name),
            (array) ($data['additionalSites'] ?? []),
        )));
        foreach ($additionalSites as $additionalSiteName) {
            $this->site($additionalSiteName);
        }

        $site = $this->site((string) $data['site']);
        $location = $this->location($site, (string) $data['location']);
        $category = $this->category((string) $data['category']);

        $itemCounts = ['created' => 0, 'updated' => 0];
        foreach ($this->rows($data['inventoryColumns'], $data['inventory']) as $row) {
            $item = $this->itemRepository->findOneBy(['reference' => $row['reference']]);
            $created = false;
            if (!$item instanceof InventoryItem) {
                $item = (new InventoryItem())
                    ->setReference((string) $row['reference'])
                    ->setLogisticsStatus('legacy_remaining');
                $created = true;
                $this->entityManager->persist($item);
            }

            $quantity = max(0, (int) $row['quantity']);
            $condition = $this->condition((string) ($row['color'] ?? ''));
            $status = $condition === 'out_of_order' ? 'maintenance' : 'available';
            $item
                ->setName((string) $row['name'])
                ->setCategory($category)
                ->setDimensions($this->nullableString($row['dimensions'] ?? null))
                ->setColor($this->nullableString($row['color'] ?? null))
                ->setUnit((string) ($row['unit'] ?: 'piece'))
                ->setCondition($condition)
                ->setNotes($this->notes((string) $data['source'], $row));

            if ($created) {
                $item
                    ->setSite($site)
                    ->setLocation($location)
                    ->setQuantity($quantity)
                    ->setAvailableQuantity($quantity)
                    ->setStatus($status);
            }

            $itemCounts[$created ? 'created' : 'updated']++;
        }

        $stockCounts = ['created' => 0, 'updated' => 0, 'lines_created' => 0, 'lines_updated' => 0, 'lines_skipped' => 0, 'lines_removed' => 0];
        foreach ($data['cartonStocks'] as $stockRow) {
            $stock = $this->cartonStockRepository->findOneByNameInsensitive((string) $stockRow['name']);
            $created = false;
            if (!$stock instanceof InventoryCartonStock) {
                $stock = new InventoryCartonStock();
                $created = true;
                $this->entityManager->persist($stock);
            }

            $stock
                ->setName((string) $stockRow['name'])
                ->setSourceSheet($this->nullableString($stockRow['sourceSheet'] ?? null))
                ->setDescription($this->nullableString($stockRow['description'] ?? null))
                ->setIsActive(true);
            $this->entityManager->flush();
            $stockCounts[$created ? 'created' : 'updated']++;

            $position = 10;
            foreach ($this->rows($data['cartonLineColumns'], $stockRow['lines']) as $lineRow) {
                if ($this->isCartonSummaryLine($lineRow)) {
                    if ($this->removeExistingCartonSummaryLine($stock, $lineRow)) {
                        $stockCounts['lines_removed']++;
                    }
                    $stockCounts['lines_skipped']++;

                    continue;
                }

                $line = $this->cartonLineRepository->findOneForFixture(
                    $stock,
                    $this->nullableString($lineRow['groupName'] ?? null),
                    (string) $lineRow['reference'],
                    (string) $lineRow['lineType'],
                );
                $lineCreated = false;
                if (!$line instanceof InventoryCartonStockLine) {
                    $line = new InventoryCartonStockLine();
                    $lineCreated = true;
                    $this->entityManager->persist($line);
                }

                $line
                    ->setStock($stock)
                    ->setGroupName($this->nullableString($lineRow['groupName'] ?? null))
                    ->setReference((string) $lineRow['reference'])
                    ->setQuantity($lineRow['quantity'] !== null ? (int) $lineRow['quantity'] : null)
                    ->setUnitPrice($lineRow['unitPrice'])
                    ->setTotalAmount($lineRow['totalAmount'])
                    ->setLineType((string) $lineRow['lineType'])
                    ->setPosition($position);
                $position += 10;

                $stockCounts[$lineCreated ? 'lines_created' : 'lines_updated']++;
            }
        }

        $this->entityManager->flush();
        $io->success(sprintf(
            'Import termine sans purge: %d materiels crees, %d materiels descriptifs mis a jour, %d sites complementaires actives, %d stocks crees, %d stocks mis a jour, %d lignes creees, %d lignes mises a jour, %d lignes total ignorees, %d anciennes lignes total supprimees. Les sites et quantites operationnels existants ont ete conserves.',
            $itemCounts['created'],
            $itemCounts['updated'],
            count($additionalSites),
            $stockCounts['created'],
            $stockCounts['updated'],
            $stockCounts['lines_created'],
            $stockCounts['lines_updated'],
            $stockCounts['lines_skipped'],
            $stockCounts['lines_removed'],
        ));

        return Command::SUCCESS;
    }

    /** @return array<string, mixed> */
    private function fixtureData(): array
    {
        $path = dirname(__DIR__, 2).'/config/import/inventory_stock_bouzaniyine.json';
        if (!is_file($path)) {
            throw new \RuntimeException(sprintf('Fixture file not found: %s', $path));
        }

        return json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
    }

    /** @return list<array<string, mixed>> */
    private function rows(array $columns, array $rows): array
    {
        return array_map(static fn (array $row): array => array_combine($columns, $row), $rows);
    }

    private function category(string $name): InventoryCategory
    {
        $category = $this->categoryRepository->findOneByNameInsensitive($name);
        if (!$category instanceof InventoryCategory) {
            $category = (new InventoryCategory())->setName($name)->setDescription('Categorie creee par les fixtures inventaire.');
            $this->entityManager->persist($category);
        }

        $category->setIsActive(true);
        $this->entityManager->flush();

        return $category;
    }

    private function site(string $name): InventorySite
    {
        $site = $this->siteRepository->findOneByNameInsensitive($name);
        if (!$site instanceof InventorySite) {
            $site = (new InventorySite())->setName($name)->setDescription('Site cree par les fixtures inventaire.');
            $this->entityManager->persist($site);
        }

        $site->setIsActive(true);
        $this->entityManager->flush();

        return $site;
    }

    private function location(InventorySite $site, string $name): InventoryLocation
    {
        $location = $this->locationRepository->findOneBy(['site' => $site, 'name' => $name]);
        if (!$location instanceof InventoryLocation) {
            $location = (new InventoryLocation())->setSite($site)->setName($name)->setDescription('Emplacement cree par les fixtures inventaire.');
            $this->entityManager->persist($location);
        }

        $location->setIsActive(true);
        $this->entityManager->flush();

        return $location;
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }

    /** @param array<string, mixed> $lineRow */
    private function isCartonSummaryLine(array $lineRow): bool
    {
        return trim((string) ($lineRow['lineType'] ?? '')) === 'summary'
            || mb_strtoupper(trim((string) ($lineRow['reference'] ?? ''))) === 'TOTAL';
    }

    /** @param array<string, mixed> $lineRow */
    private function removeExistingCartonSummaryLine(InventoryCartonStock $stock, array $lineRow): bool
    {
        $removed = false;
        $groupName = $this->nullableString($lineRow['groupName'] ?? null);
        $reference = (string) ($lineRow['reference'] ?? 'TOTAL');

        foreach (array_values(InventoryCartonStockLine::LINE_TYPES) as $lineType) {
            $line = $this->cartonLineRepository->findOneForFixture($stock, $groupName, $reference, $lineType);
            if (!$line instanceof InventoryCartonStockLine) {
                continue;
            }

            $this->entityManager->remove($line);
            $removed = true;
        }

        return $removed;
    }

    private function condition(string $color): string
    {
        $color = mb_strtolower($color);

        return str_contains($color, 'non fonctionnel') || str_contains($color, 'sans roue') ? 'out_of_order' : 'good';
    }

    /** @param array<string, mixed> $row */
    private function notes(string $source, array $row): ?string
    {
        $rawQuantity = trim((string) ($row['rawQuantity'] ?? ''));
        $quantity = (string) ($row['quantity'] ?? '');
        $unit = (string) ($row['unit'] ?? 'piece');
        $notes = ['Source Excel: '.$source];
        if ($rawQuantity !== '' && $rawQuantity !== $quantity && $rawQuantity !== trim($quantity.' '.$unit)) {
            $notes[] = 'Quantite source: '.$rawQuantity;
        }

        return implode("\n", $notes);
    }
}
