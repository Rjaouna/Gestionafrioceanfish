<?php

namespace App\Command;

use App\Entity\FactoryUnit;
use App\Entity\FishReception;
use App\Entity\FishReceptionStorageMovement;
use App\Entity\User;
use App\Repository\FactoryUnitRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:fish-reception:inject-july-2026',
    description: 'Simulates or injects the July 2026 reception dataset with Anchois workflow.',
)]
final class InjectJulyFishReceptionsCommand extends Command
{
    private const BOXES_PER_PALLET = 80;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly FactoryUnitRepository $factoryUnitRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('force', null, InputOption::VALUE_NONE, 'Write changes to the database. Without this option the command only simulates.')
            ->addOption('append', null, InputOption::VALUE_NONE, 'Keep existing receptions and only add missing generated numbers.')
            ->addOption('year', null, InputOption::VALUE_REQUIRED, 'Year used for dates and generated numbers.', '2026')
            ->addOption('user', null, InputOption::VALUE_REQUIRED, 'Optional user email to attach as reception actor.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $force = (bool) $input->getOption('force');
        $append = (bool) $input->getOption('append');
        $year = max(2000, (int) $input->getOption('year'));
        $actor = $this->findActor($input->getOption('user'));

        $chambre1 = $this->factorySpace('CHN-0001', FactoryUnit::TYPE_NEGATIVE_ROOM, '1', 'Chambre negative 1');
        $chambre2 = $this->factorySpace('CHN-0002', FactoryUnit::TYPE_NEGATIVE_ROOM, '2', 'Chambre negative 2');
        $tunnel1 = $this->factorySpace('TUN-0001', FactoryUnit::TYPE_TUNNEL, '1', 'Tunnel 1');
        $tunnel2 = $this->factorySpace('TUN-0002', FactoryUnit::TYPE_TUNNEL, '2', 'Tunnel 2');

        $rows = $this->dataset($year, $chambre1['value'], $chambre2['value'], $tunnel1['value'], $tunnel2['value']);

        $io->title('Injection receptions poisson - jeu terrain juillet '.$year);
        $io->section('Pieces usine utilisees');
        $io->table(
            ['Usage', 'Valeur stockee', 'Piece detectee'],
            [
                ['Chambre negative 1', $chambre1['value'], $chambre1['label']],
                ['Chambre negative 2', $chambre2['value'], $chambre2['label']],
                ['Tunnel 1', $tunnel1['value'], $tunnel1['label']],
                ['Tunnel 2', $tunnel2['value'], $tunnel2['label']],
            ],
        );

        $io->section('Donnees qui seront injectees');
        $io->table(
            ['Reception', 'Date', 'Produit', 'BL kg', 'Recu kg', 'Statut', 'A gerer ensuite', 'Note'],
            array_map(static fn (array $row): array => [
                $row['numeroReception'],
                $row['dateReception'],
                $row['especePoisson'],
                number_format((float) $row['quantiteIndiqueeBl'], 3, ',', ' '),
                number_format((float) $row['quantiteReceptionnee'], 3, ',', ' '),
                FishReception::STATUS_LABELS[$row['statut'] ?? FishReception::STATUS_RECEIVED] ?? 'Receptionnee',
                $row['destinationPrevue'],
                $row['resume'],
            ], $rows),
        );

        $io->note([
            'Dates interpretees : receptions 27/29/30 juin '.$year.', productions Anchois du 01/07 au 07/07/'.$year.'.',
            'Maquereau : cree uniquement en reception, sans workflow valide.',
            'Anchois 2000/3000 : workflow simule avec stockage initial, sorties traitement, tunnel 1, cristallisation et emballage partiel de 700 kg.',
            'Hypothese retenue : production quotidienne 600 kg, dernier jour 604 kg ; REC-0003 traitee a 2000 kg, REC-0004 traitee a 2204 kg, reste REC-0004 796 kg.',
        ]);

        if (!$force) {
            $io->warning('Simulation uniquement : rien n a ete modifie. Ajoute --force pour injecter en base.');

            return Command::SUCCESS;
        }

        if ($actor === null && trim((string) $input->getOption('user')) !== '') {
            $io->warning('Utilisateur introuvable : les champs utilisateur seront laisses vides.');
        }

        $connection = $this->entityManager->getConnection();
        $created = 0;
        $skipped = 0;
        $purged = 0;

        $connection->beginTransaction();
        try {
            if (!$append) {
                $purged = (int) $connection->fetchOne('SELECT COUNT(*) FROM fish_reception');
                $connection->executeStatement('UPDATE cout_revient SET reception_id = NULL WHERE reception_id IS NOT NULL');
                $connection->executeStatement('DELETE FROM fish_reception_storage_movement');
                $connection->executeStatement('DELETE FROM fish_reception');
            }

            foreach ($rows as $row) {
                $existing = $this->entityManager->getRepository(FishReception::class)->findOneBy([
                    'numeroReception' => $row['numeroReception'],
                ]);

                if ($existing instanceof FishReception) {
                    ++$skipped;
                    continue;
                }

                $this->entityManager->persist($this->buildReception($row, $actor));
                ++$created;
            }

            $this->entityManager->flush();
            $connection->commit();
        } catch (\Throwable $exception) {
            $connection->rollBack();

            throw $exception;
        }

        $io->success(sprintf(
            'Injection terminee : %d reception(s) supprimee(s), %d reception(s) creee(s), %d deja existante(s) ignoree(s).',
            $purged,
            $created,
            $skipped,
        ));

        return Command::SUCCESS;
    }

    /**
     * @return array{value: string, label: string}
     */
    private function factorySpace(string $preferredCode, string $type, string $number, string $fallback): array
    {
        $unit = $this->factoryUnitRepository->findOneBy(['code' => $preferredCode]);
        if ($unit instanceof FactoryUnit) {
            return [
                'value' => (string) $unit->getCode(),
                'label' => $unit->getDisplayName(),
            ];
        }

        foreach ($this->factoryUnitRepository->search('', $type) as $candidate) {
            $haystack = $this->normalize($candidate->getCode().' '.$candidate->getName().' '.$candidate->getDisplayName());
            if (str_contains($haystack, $this->normalize($number))) {
                return [
                    'value' => (string) $candidate->getCode(),
                    'label' => $candidate->getDisplayName(),
                ];
            }
        }

        return [
            'value' => $preferredCode,
            'label' => $fallback.' (fallback : code '.$preferredCode.' non trouve en base)',
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function dataset(int $year, string $chambre1, string $chambre2, string $tunnel1, string $tunnel2): array
    {
        $maquereau1Stock = round(5060 * 0.96, 3);
        $maquereau2Stock = round(8043 * 0.96, 3);
        $anchois1Prepared = 2000.000;
        $anchois1Finished = 1014.000;
        $anchois1Waste = 686.667;
        $anchois1Loss = round($anchois1Prepared - $anchois1Finished - $anchois1Waste, 3);
        $anchois2Prepared = 2204.000;
        $anchois2Finished = 1214.000;
        $anchois2Waste = 667.333;
        $anchois2Loss = round($anchois2Prepared - $anchois2Finished - $anchois2Waste, 3);
        $packagedGross = 700.000;
        $packagingLoss = 28.000;
        $packagedNet = $packagedGross - $packagingLoss;

        return [
            $this->row([
                'numeroReception' => sprintf('REC-%d-0001', $year),
                'numeroLot' => sprintf('LOT-%d-0001', $year),
                'dateReception' => sprintf('%d-06-27', $year),
                'heureDebutReception' => '19:00',
                'heureFinReception' => '20:15',
                'fournisseur' => 'Non renseigne',
                'provenance' => 'Donnees terrain import production',
                'especePoisson' => 'Maquereau',
                'presentationProduit' => 'Frais avec glace',
                'etatProduit' => 'Frais avec glace',
                'numeroBonLivraison' => sprintf('BL-%d-06-27-01', $year),
                'quantiteIndiqueeBl' => 5060,
                'quantiteReceptionnee' => 6776,
                'quantiteTotalePreparee' => 6776,
                'quantiteConditionnee' => $maquereau1Stock,
                'quantiteCongelee' => $maquereau1Stock,
                'quantiteStockee' => $maquereau1Stock,
                'quantiteUtiliseeProduction' => 604,
                'poidsMoyenParCaisse' => 11,
                'nombreMoules' => 8,
                'temperaturePoissonReception' => 2,
                'temperatureEauGlacee' => -2,
                'presenceGlace' => true,
                'categorieFraicheur' => 'A',
                'dateDebutTraitement' => sprintf('%d-07-01', $year),
                'heureDebutTraitement' => '08:00',
                'dateConditionnement' => sprintf('%d-07-02', $year),
                'heureDebutConditionnement' => '09:00',
                'heureFinConditionnement' => '15:30',
                'produitConditionne' => 'Maquereau traite',
                'tunnel' => $tunnel1,
                'heureEntreeTunnel' => '16:00',
                'heureSortieTunnel' => '19:00',
                'dateSortieTunnel' => sprintf('%d-07-02', $year),
                'temperatureTunnel' => -40,
                'temperatureCoeurProduit' => -18,
                'chambreFroide' => $chambre1,
                'dateEntreeStockage' => sprintf('%d-07-02', $year),
                'heureEntreeStockage' => '17:30',
                'temperatureChambre' => -20,
                'temperatureStockage' => -18,
                'resume' => 'BL 5060 kg, reel 6776 kg avec glace ; stock estime 96 % BL.',
                'observations' => 'Reception terrain du 27 soir : Maquereau frais avec glace. Poids BL 5060 kg, poids réel 6776 kg. Stock estimé apres retrait glace/déchets : 4857.600 kg (96 % du BL). Episode terrain du 01/07 au 02/07 : 604 kg traites ; le 04/07 sortie 305 kg, produit fini 167 kg, déchets/pertes estimes 138 kg dont 110.400 kg déchets et 27.600 kg pertes/glace/eau. Le reste est conserve en stockage selon les informations fournies.',
            ]),
            $this->row([
                'numeroReception' => sprintf('REC-%d-0002', $year),
                'numeroLot' => sprintf('LOT-%d-0002', $year),
                'dateReception' => sprintf('%d-06-29', $year),
                'heureDebutReception' => '09:20',
                'heureFinReception' => '10:45',
                'fournisseur' => 'Non renseigne',
                'provenance' => 'Donnees terrain import production',
                'especePoisson' => 'Maquereau',
                'presentationProduit' => 'Frais',
                'etatProduit' => 'Frais',
                'numeroBonLivraison' => sprintf('BL-%d-06-29-01', $year),
                'quantiteIndiqueeBl' => 8043,
                'quantiteReceptionnee' => 8043,
                'quantiteTotalePreparee' => 8043,
                'quantiteConditionnee' => $maquereau2Stock,
                'quantiteCongelee' => $maquereau2Stock,
                'quantiteStockee' => $maquereau2Stock,
                'poidsMoyenParCaisse' => 11,
                'nombreMoules' => 8,
                'temperaturePoissonReception' => 2,
                'temperatureEauGlacee' => -2,
                'presenceGlace' => true,
                'categorieFraicheur' => 'A',
                'dateDebutTraitement' => sprintf('%d-07-02', $year),
                'heureDebutTraitement' => '08:30',
                'dateConditionnement' => sprintf('%d-07-02', $year),
                'heureDebutConditionnement' => '10:00',
                'heureFinConditionnement' => '16:45',
                'produitConditionne' => 'Maquereau traite',
                'tunnel' => $tunnel2,
                'heureEntreeTunnel' => '17:00',
                'heureSortieTunnel' => '20:00',
                'dateSortieTunnel' => sprintf('%d-07-02', $year),
                'temperatureTunnel' => -40,
                'temperatureCoeurProduit' => -18,
                'chambreFroide' => $chambre1,
                'dateEntreeStockage' => sprintf('%d-07-02', $year),
                'heureEntreeStockage' => '18:15',
                'temperatureChambre' => -20,
                'temperatureStockage' => -18,
                'resume' => 'BL et reel 8043 kg ; stock estime 96 % BL.',
                'observations' => 'Reception terrain du 29 : Maquereau frais. Poids BL 8043 kg, poids réel 8043 kg. Stock estimé apres retrait déchets/glace eventuelle : 7721.280 kg (96 % du BL). Stockage estime en Chambre negative 1 faute de chambre precise dans la note.',
            ]),
            $this->row([
                'numeroReception' => sprintf('REC-%d-0003', $year),
                'numeroLot' => sprintf('LOT-%d-0003', $year),
                'dateReception' => sprintf('%d-06-29', $year),
                'heureDebutReception' => '16:00',
                'heureFinReception' => '17:00',
                'fournisseur' => 'Non renseigne',
                'provenance' => 'Donnees terrain import production',
                'especePoisson' => 'Anchois',
                'presentationProduit' => 'Recu congele',
                'etatProduit' => 'Congelé',
                'numeroBonLivraison' => sprintf('BL-%d-06-29-02', $year),
                'quantiteIndiqueeBl' => 2000,
                'quantiteReceptionnee' => 2000,
                'poidsMoyenParCaisse' => 20,
                'nombreMoules' => 0,
                'temperaturePoissonReception' => -18,
                'presenceGlace' => false,
                'categorieFraicheur' => 'Congele',
                'simulateWorkflow' => true,
                'statut' => FishReception::STATUS_RETURNED_TO_ROOM,
                'quantiteTotalePreparee' => $anchois1Prepared,
                'quantiteConditionnee' => $packagedGross,
                'quantiteCongelee' => $anchois1Finished,
                'quantiteStockee' => $anchois1Finished,
                'quantiteUtiliseeProduction' => $anchois1Prepared,
                'poidsDechetsTraitement' => $anchois1Waste,
                'poidsPertesTraitement' => $anchois1Loss,
                'poidsDechetsEmballage' => 0,
                'poidsPertesEmballage' => $packagingLoss,
                'poidsNet' => $packagedNet,
                'quantiteRemiseEnChambre' => $packagedGross,
                'temperatureEauGlacee' => -2,
                'nombreCaissesApresTraitement' => (int) ceil($anchois1Finished / 20),
                'nombreCaissesParPalette' => self::BOXES_PER_PALLET,
                'nombreTotalPalettes' => 1,
                'dateDebutTraitement' => sprintf('%d-07-01', $year),
                'heureDebutTraitement' => '08:00',
                'dateConditionnement' => sprintf('%d-07-07', $year),
                'heureDebutConditionnement' => '12:00',
                'heureFinConditionnement' => '18:42',
                'produitConditionne' => 'Anchois emballe',
                'tunnel' => $tunnel1,
                'dateEntreeTunnel' => sprintf('%d-07-06', $year),
                'heureEntreeTunnel' => '10:30',
                'heureSortieTunnel' => '11:45',
                'dateSortieTunnel' => sprintf('%d-07-06', $year),
                'temperatureTunnel' => -32,
                'temperatureCoeurProduit' => -1,
                'chambreFroide' => $chambre1,
                'dateEntreeStockage' => sprintf('%d-07-06', $year),
                'heureEntreeStockage' => '12:05',
                'temperatureChambre' => -18,
                'temperatureStockage' => -1,
                'chambreRemiseEnChambre' => $chambre1,
                'dateRemiseEnChambre' => sprintf('%d-07-07', $year),
                'heureRemiseEnChambre' => '18:50',
                'temperatureChambreRemise' => -18,
                'temperatureProduitRemise' => 0,
                'storageMovements' => [
                    [
                        'stage' => FishReceptionStorageMovement::STAGE_INITIAL,
                        'type' => FishReceptionStorageMovement::TYPE_INITIAL_ENTRY,
                        'location' => $chambre1,
                        'quantity' => 2000,
                        'date' => sprintf('%d-06-29', $year),
                        'time' => '17:10',
                        'temperatureChamber' => -18,
                        'temperatureProduct' => -18,
                        'note' => 'Stockage initial Anchois 2000 kg en chambre negative 1.',
                    ],
                    [
                        'stage' => FishReceptionStorageMovement::STAGE_INITIAL,
                        'type' => FishReceptionStorageMovement::TYPE_INITIAL_EXIT,
                        'location' => $chambre1,
                        'quantity' => -600,
                        'date' => sprintf('%d-07-01', $year),
                        'time' => '08:00',
                        'temperatureChamber' => -18,
                        'temperatureProduct' => -1,
                        'note' => 'Traitement 600 kg : PF 288 kg, dechets 210 kg, pertes 102 kg. Dechets estimes faute de pesee detaillee pour le 01/07.',
                    ],
                    [
                        'stage' => FishReceptionStorageMovement::STAGE_INITIAL,
                        'type' => FishReceptionStorageMovement::TYPE_INITIAL_EXIT,
                        'location' => $chambre1,
                        'quantity' => -600,
                        'date' => sprintf('%d-07-02', $year),
                        'time' => '08:00',
                        'temperatureChamber' => -18,
                        'temperatureProduct' => -1,
                        'note' => 'Traitement 600 kg : PF 300 kg, dechets 266 kg, pertes 34 kg.',
                    ],
                    [
                        'stage' => FishReceptionStorageMovement::STAGE_INITIAL,
                        'type' => FishReceptionStorageMovement::TYPE_INITIAL_EXIT,
                        'location' => $chambre1,
                        'quantity' => -600,
                        'date' => sprintf('%d-07-03', $year),
                        'time' => '08:10',
                        'temperatureChamber' => -18,
                        'temperatureProduct' => -1,
                        'note' => 'Traitement 600 kg : PF 318 kg, dechets 175 kg, pertes 107 kg.',
                    ],
                    [
                        'stage' => FishReceptionStorageMovement::STAGE_INITIAL,
                        'type' => FishReceptionStorageMovement::TYPE_INITIAL_EXIT,
                        'location' => $chambre1,
                        'quantity' => -200,
                        'date' => sprintf('%d-07-04', $year),
                        'time' => '08:05',
                        'temperatureChamber' => -18,
                        'temperatureProduct' => 0,
                        'note' => 'Traitement 200 kg : PF 108 kg, dechets 35.667 kg, pertes 56.333 kg. Complement du 04/07 pris sur la reception suivante.',
                    ],
                ],
                'resume' => 'Anchois 2000 kg : workflow simule, 700 kg emballes, 672 kg net.',
                'observations' => 'Reception terrain du 29 : Anchois 2000 kg BL, 2000 kg reel, recu congele puis stocke en Chambre negative 1. Simulation traitement : 01/07 600 kg, 02/07 600 kg, 03/07 600 kg, 04/07 200 kg. Totaux : prepare 2000 kg, produit fini 1014 kg, dechets 686.667 kg, pertes 299.333 kg. Le 01/07 utilise une estimation de dechets car aucune pesee detaillee n a ete fournie pour ce jour. Tunnel 1 entre 45 min et 1h15 par production, temperature tunnel simulee -32 deg C, eau -2 deg C, coeur produit -1 deg C. Emballage du 07/07 : 700 kg, perte 28 kg, poids net 672 kg, retour en chambre negative 1.',
            ]),
            $this->row([
                'numeroReception' => sprintf('REC-%d-0004', $year),
                'numeroLot' => sprintf('LOT-%d-0004', $year),
                'dateReception' => sprintf('%d-06-30', $year),
                'heureDebutReception' => '10:00',
                'heureFinReception' => '11:00',
                'fournisseur' => 'Non renseigne',
                'provenance' => 'Donnees terrain import production',
                'especePoisson' => 'Anchois',
                'presentationProduit' => 'Recu congele',
                'etatProduit' => 'Congelé',
                'numeroBonLivraison' => sprintf('BL-%d-06-30-01', $year),
                'quantiteIndiqueeBl' => 3000,
                'quantiteReceptionnee' => 3000,
                'poidsMoyenParCaisse' => 20,
                'nombreMoules' => 0,
                'temperaturePoissonReception' => -18,
                'presenceGlace' => false,
                'categorieFraicheur' => 'Congele',
                'simulateWorkflow' => true,
                'statut' => FishReception::STATUS_STORED,
                'quantiteTotalePreparee' => $anchois2Prepared,
                'quantiteConditionnee' => 0,
                'quantiteCongelee' => $anchois2Finished,
                'quantiteStockee' => $anchois2Finished,
                'quantiteUtiliseeProduction' => $anchois2Prepared,
                'poidsDechetsTraitement' => $anchois2Waste,
                'poidsPertesTraitement' => $anchois2Loss,
                'poidsDechetsEmballage' => 0,
                'poidsPertesEmballage' => 0,
                'poidsNet' => 0,
                'quantiteRemiseEnChambre' => 0,
                'temperatureEauGlacee' => -2,
                'nombreCaissesApresTraitement' => (int) ceil($anchois2Finished / 20),
                'nombreCaissesParPalette' => self::BOXES_PER_PALLET,
                'nombreTotalPalettes' => 1,
                'dateDebutTraitement' => sprintf('%d-07-04', $year),
                'heureDebutTraitement' => '08:25',
                'dateConditionnement' => null,
                'heureDebutConditionnement' => null,
                'heureFinConditionnement' => null,
                'produitConditionne' => null,
                'tunnel' => $tunnel1,
                'dateEntreeTunnel' => sprintf('%d-07-07', $year),
                'heureEntreeTunnel' => '10:45',
                'heureSortieTunnel' => '12:00',
                'dateSortieTunnel' => sprintf('%d-07-07', $year),
                'temperatureTunnel' => -28,
                'temperatureCoeurProduit' => 0,
                'chambreFroide' => $chambre2,
                'dateEntreeStockage' => sprintf('%d-07-07', $year),
                'heureEntreeStockage' => '12:15',
                'temperatureChambre' => -18,
                'temperatureStockage' => 0,
                'storageMovements' => [
                    [
                        'stage' => FishReceptionStorageMovement::STAGE_INITIAL,
                        'type' => FishReceptionStorageMovement::TYPE_INITIAL_ENTRY,
                        'location' => $chambre2,
                        'quantity' => 3000,
                        'date' => sprintf('%d-06-30', $year),
                        'time' => '11:15',
                        'temperatureChamber' => -18,
                        'temperatureProduct' => -18,
                        'note' => 'Stockage initial Anchois 3000 kg en chambre negative 2.',
                    ],
                    [
                        'stage' => FishReceptionStorageMovement::STAGE_INITIAL,
                        'type' => FishReceptionStorageMovement::TYPE_INITIAL_EXIT,
                        'location' => $chambre2,
                        'quantity' => -400,
                        'date' => sprintf('%d-07-04', $year),
                        'time' => '08:25',
                        'temperatureChamber' => -18,
                        'temperatureProduct' => 0,
                        'note' => 'Traitement 400 kg : PF 216 kg, dechets 71.333 kg, pertes 112.667 kg. Complement pour totaliser 600 kg traites le 04/07.',
                    ],
                    [
                        'stage' => FishReceptionStorageMovement::STAGE_INITIAL,
                        'type' => FishReceptionStorageMovement::TYPE_INITIAL_EXIT,
                        'location' => $chambre2,
                        'quantity' => -600,
                        'date' => sprintf('%d-07-05', $year),
                        'time' => '08:00',
                        'temperatureChamber' => -18,
                        'temperatureProduct' => 0,
                        'note' => 'Traitement 600 kg : PF 330 kg, dechets 180 kg, pertes 90 kg. Dechets estimes faute de pesee detaillee pour le 05/07.',
                    ],
                    [
                        'stage' => FishReceptionStorageMovement::STAGE_INITIAL,
                        'type' => FishReceptionStorageMovement::TYPE_INITIAL_EXIT,
                        'location' => $chambre2,
                        'quantity' => -600,
                        'date' => sprintf('%d-07-06', $year),
                        'time' => '08:00',
                        'temperatureChamber' => -18,
                        'temperatureProduct' => 0,
                        'note' => 'Traitement 600 kg : PF 330 kg, dechets 211 kg, pertes 59 kg.',
                    ],
                    [
                        'stage' => FishReceptionStorageMovement::STAGE_INITIAL,
                        'type' => FishReceptionStorageMovement::TYPE_INITIAL_EXIT,
                        'location' => $chambre2,
                        'quantity' => -604,
                        'date' => sprintf('%d-07-07', $year),
                        'time' => '08:15',
                        'temperatureChamber' => -18,
                        'temperatureProduct' => 0,
                        'note' => 'Traitement 604 kg : PF 338 kg, dechets 205 kg, pertes 61 kg.',
                    ],
                ],
                'resume' => 'Anchois 3000 kg : workflow simule, reste MP calcule 796 kg.',
                'observations' => 'Reception terrain du 30 : Anchois 3000 kg BL, 3000 kg reel, recu congele puis stocke en Chambre negative 2. Simulation traitement : 04/07 400 kg, 05/07 600 kg, 06/07 600 kg, 07/07 604 kg. Totaux : prepare 2204 kg, produit fini 1214 kg, dechets 667.333 kg, pertes 322.667 kg. Le 05/07 utilise une estimation de dechets car aucune pesee detaillee n a ete fournie pour ce jour. Tunnel 1 entre 45 min et 1h15 par production, temperature tunnel simulee -28 deg C, eau -2 deg C, coeur produit 0 deg C. Stock MP restant calcule : 796 kg.',
            ]),
        ];
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    private function row(array $data): array
    {
        $received = (float) $data['quantiteReceptionnee'];
        $boxWeight = max(0.001, (float) ($data['poidsMoyenParCaisse'] ?? 1));
        $simulateWorkflow = (bool) ($data['simulateWorkflow'] ?? false);
        $plannedSteps = [];
        if (trim((string) ($data['tunnel'] ?? '')) !== '') {
            $plannedSteps[] = 'tunnel prevu '.$data['tunnel'];
        }
        if (trim((string) ($data['chambreFroide'] ?? '')) !== '') {
            $plannedSteps[] = 'stockage prevu '.$data['chambreFroide'];
        }

        $data['nombreCaissesReception'] = (int) ceil($received / $boxWeight);
        $data['destinationPrevue'] = $plannedSteps !== [] ? implode(' / ', $plannedSteps) : 'A definir depuis l interface';
        $data['observations'] = trim((string) ($data['observations'] ?? ''));

        $data += [
            'statut' => FishReception::STATUS_RECEIVED,
            'quantiteTotalePreparee' => 0,
            'quantiteConditionnee' => 0,
            'quantiteCongelee' => 0,
            'quantiteStockee' => 0,
            'quantiteUtiliseeProduction' => 0,
            'dateDebutTraitement' => null,
            'heureDebutTraitement' => null,
            'temperatureEauGlacee' => null,
            'nombreCaissesApresTraitement' => 0,
            'poidsMoyenParCaisse' => 0,
            'nombreMoules' => 0,
            'nombreCaissesParPalette' => 0,
            'nombreTotalPalettes' => 0,
            'poidsDechetsTraitement' => 0,
            'poidsPertesTraitement' => 0,
            'dateConditionnement' => null,
            'heureDebutConditionnement' => null,
            'heureFinConditionnement' => null,
            'produitConditionne' => null,
            'poidsNet' => 0,
            'poidsDechetsEmballage' => 0,
            'poidsPertesEmballage' => 0,
            'tunnel' => null,
            'dateEntreeTunnel' => null,
            'heureEntreeTunnel' => null,
            'heureSortieTunnel' => null,
            'temperatureTunnel' => null,
            'dateSortieTunnel' => null,
            'temperatureCoeurProduit' => null,
            'chambreFroide' => null,
            'temperatureChambre' => null,
            'dateEntreeStockage' => null,
            'heureEntreeStockage' => null,
            'temperatureStockage' => null,
            'chambreRemiseEnChambre' => null,
            'dateRemiseEnChambre' => null,
            'heureRemiseEnChambre' => null,
            'temperatureChambreRemise' => null,
            'temperatureProduitRemise' => null,
            'quantiteRemiseEnChambre' => 0,
            'storageMovements' => [],
        ];

        if (!$simulateWorkflow) {
            $data['observations'] .= ' Import volontairement limite a l etape reception : lancer le traitement, le conditionnement, la congelation, le stockage et l expedition depuis l interface, cas par cas. '.$data['destinationPrevue'].'.';
            $data['statut'] = FishReception::STATUS_RECEIVED;
            $data['quantiteTotalePreparee'] = 0;
            $data['quantiteConditionnee'] = 0;
            $data['quantiteCongelee'] = 0;
            $data['quantiteStockee'] = 0;
            $data['quantiteUtiliseeProduction'] = 0;
            $data['dateDebutTraitement'] = null;
            $data['heureDebutTraitement'] = null;
            $data['temperatureEauGlacee'] = null;
            $data['nombreCaissesApresTraitement'] = 0;
            $data['poidsMoyenParCaisse'] = 0;
            $data['nombreMoules'] = 0;
            $data['nombreCaissesParPalette'] = 0;
            $data['nombreTotalPalettes'] = 0;
            $data['poidsDechetsTraitement'] = 0;
            $data['poidsPertesTraitement'] = 0;
            $data['dateConditionnement'] = null;
            $data['heureDebutConditionnement'] = null;
            $data['heureFinConditionnement'] = null;
            $data['produitConditionne'] = null;
            $data['poidsNet'] = 0;
            $data['poidsDechetsEmballage'] = 0;
            $data['poidsPertesEmballage'] = 0;
            $data['tunnel'] = null;
            $data['dateEntreeTunnel'] = null;
            $data['heureEntreeTunnel'] = null;
            $data['heureSortieTunnel'] = null;
            $data['temperatureTunnel'] = null;
            $data['dateSortieTunnel'] = null;
            $data['temperatureCoeurProduit'] = null;
            $data['chambreFroide'] = null;
            $data['temperatureChambre'] = null;
            $data['dateEntreeStockage'] = null;
            $data['heureEntreeStockage'] = null;
            $data['temperatureStockage'] = null;
            $data['chambreRemiseEnChambre'] = null;
            $data['dateRemiseEnChambre'] = null;
            $data['heureRemiseEnChambre'] = null;
            $data['temperatureChambreRemise'] = null;
            $data['temperatureProduitRemise'] = null;
            $data['quantiteRemiseEnChambre'] = 0;
            $data['storageMovements'] = [];
        } else {
            $data['observations'] .= ' Workflow simule depuis les donnees terrain : mouvements de stock initial, traitement, tunnel, cristallisation et emballage partiel selon les informations fournies.';
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function buildReception(array $row, ?User $actor): FishReception
    {
        $reception = (new FishReception())
            ->setNumeroReception($row['numeroReception'])
            ->setNumeroLot($row['numeroLot'])
            ->setDateReception($this->date($row['dateReception']))
            ->setHeureDebutReception($this->time($row['heureDebutReception']))
            ->setHeureFinReception($this->time($row['heureFinReception']))
            ->setFournisseur($row['fournisseur'])
            ->setProvenance($row['provenance'])
            ->setEspecePoisson($row['especePoisson'])
            ->setPresentationProduit($row['presentationProduit'])
            ->setEtatProduit($row['etatProduit'])
            ->setNumeroBonLivraison($row['numeroBonLivraison'])
            ->setQuantiteIndiqueeBl($row['quantiteIndiqueeBl'])
            ->setQuantiteReceptionnee($row['quantiteReceptionnee'])
            ->setNombreCaissesReception($row['nombreCaissesReception'])
            ->setTemperaturePoissonReception($row['temperaturePoissonReception'])
            ->setCategorieFraicheur($row['categorieFraicheur'])
            ->setPresenceGlace($row['presenceGlace'])
            ->setDateDebutTraitement($this->date($row['dateDebutTraitement']))
            ->setHeureDebutTraitement($this->time($row['heureDebutTraitement']))
            ->setTemperatureEauGlacee($row['temperatureEauGlacee'])
            ->setNombreCaissesApresTraitement($row['nombreCaissesApresTraitement'])
            ->setPoidsMoyenParCaisse($row['poidsMoyenParCaisse'])
            ->setNombreMoules($row['nombreMoules'])
            ->setNombreCaissesParPalette($row['nombreCaissesParPalette'])
            ->setNombreTotalPalettes($row['nombreTotalPalettes'])
            ->setQuantiteTotalePreparee($row['quantiteTotalePreparee'])
            ->setPoidsDechetsTraitement($row['poidsDechetsTraitement'])
            ->setPoidsPertesTraitement($row['poidsPertesTraitement'])
            ->setDateConditionnement($this->date($row['dateConditionnement']))
            ->setHeureDebutConditionnement($this->time($row['heureDebutConditionnement']))
            ->setHeureFinConditionnement($this->time($row['heureFinConditionnement']))
            ->setProduitConditionne($row['produitConditionne'])
            ->setQuantiteConditionnee($row['quantiteConditionnee'])
            ->setPoidsNet($row['poidsNet'])
            ->setPoidsDechetsEmballage($row['poidsDechetsEmballage'])
            ->setPoidsPertesEmballage($row['poidsPertesEmballage'])
            ->setTunnel($row['tunnel'])
            ->setDateEntreeTunnel($this->date($row['dateEntreeTunnel']))
            ->setHeureEntreeTunnel($this->time($row['heureEntreeTunnel']))
            ->setHeureSortieTunnel($this->time($row['heureSortieTunnel'] ?? null))
            ->setTemperatureTunnel($row['temperatureTunnel'])
            ->setDateSortieTunnel($this->date($row['dateSortieTunnel']))
            ->setTemperatureCoeurProduit($row['temperatureCoeurProduit'])
            ->setQuantiteCongelee($row['quantiteCongelee'])
            ->setChambreFroide($row['chambreFroide'])
            ->setTemperatureChambre($row['temperatureChambre'])
            ->setDateEntreeStockage($this->date($row['dateEntreeStockage']))
            ->setHeureEntreeStockage($this->time($row['heureEntreeStockage']))
            ->setQuantiteStockee($row['quantiteStockee'])
            ->setTemperatureStockage($row['temperatureStockage'])
            ->setChambreRemiseEnChambre($row['chambreRemiseEnChambre'])
            ->setDateRemiseEnChambre($this->date($row['dateRemiseEnChambre']))
            ->setHeureRemiseEnChambre($this->time($row['heureRemiseEnChambre']))
            ->setTemperatureChambreRemise($row['temperatureChambreRemise'])
            ->setTemperatureProduitRemise($row['temperatureProduitRemise'])
            ->setQuantiteRemiseEnChambre($row['quantiteRemiseEnChambre'])
            ->setQuantiteUtiliseeProduction($row['quantiteUtiliseeProduction'] ?? 0)
            ->setStatut($row['statut'])
            ->setReceivedAt($this->dateTime($row['dateReception'], $row['heureFinReception']))
            ->setTreatmentStartedAt($this->dateTime($row['dateDebutTraitement'], $row['heureDebutTraitement']))
            ->setStoredAt($this->dateTime($row['dateEntreeStockage'], $row['heureEntreeStockage']))
            ->setRemiseEnChambreAt($this->dateTime($row['dateRemiseEnChambre'], $row['heureRemiseEnChambre']))
            ->setObservations($row['observations']);

        if ($actor instanceof User) {
            $reception
                ->setCreatedBy($actor)
                ->setReceivedBy($actor);

            if ($row['dateDebutTraitement'] !== null) {
                $reception->setTreatmentStartedBy($actor);
            }

            if ($row['dateEntreeStockage'] !== null) {
                $reception->setStoredBy($actor);
            }

            if ($row['dateRemiseEnChambre'] !== null) {
                $reception->setRemiseEnChambreBy($actor);
            }
        }

        foreach ($row['storageMovements'] as $movementRow) {
            $reception->addStorageMovement($this->buildStorageMovement($movementRow, $actor));
        }

        return $reception;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function buildStorageMovement(array $row, ?User $actor): FishReceptionStorageMovement
    {
        $movement = (new FishReceptionStorageMovement())
            ->setStorageStage($row['stage'])
            ->setMovementType($row['type'])
            ->setLocation($row['location'])
            ->setQuantityKg($row['quantity'])
            ->setMovementDate($this->date($row['date']))
            ->setMovementTime($this->time($row['time'] ?? null))
            ->setTemperatureChamber($row['temperatureChamber'] ?? null)
            ->setTemperatureProduct($row['temperatureProduct'] ?? null)
            ->setNote($row['note'] ?? null);

        if ($actor instanceof User) {
            $movement->setCreatedBy($actor);
        }

        return $movement;
    }

    private function findActor(mixed $email): ?User
    {
        $email = mb_strtolower(trim((string) $email));
        if ($email === '') {
            return null;
        }

        $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);

        return $user instanceof User ? $user : null;
    }

    private function date(mixed $date): ?\DateTimeImmutable
    {
        $date = trim((string) $date);

        return $date !== '' ? new \DateTimeImmutable($date) : null;
    }

    private function time(mixed $time): ?\DateTimeImmutable
    {
        $time = trim((string) $time);

        return $time !== '' ? new \DateTimeImmutable($time) : null;
    }

    private function dateTime(mixed $date, mixed $time): ?\DateTimeImmutable
    {
        $date = trim((string) $date);
        $time = trim((string) $time);
        if ($date === '') {
            return null;
        }

        return new \DateTimeImmutable($date.' '.($time !== '' ? $time : '00:00'));
    }

    private function normalize(string $value): string
    {
        $value = mb_strtolower(trim($value));

        return strtr($value, [
            'à' => 'a',
            'á' => 'a',
            'â' => 'a',
            'ä' => 'a',
            'ç' => 'c',
            'è' => 'e',
            'é' => 'e',
            'ê' => 'e',
            'ë' => 'e',
            'î' => 'i',
            'ï' => 'i',
            'ô' => 'o',
            'ö' => 'o',
            'ù' => 'u',
            'û' => 'u',
            'ü' => 'u',
        ]);
    }
}
