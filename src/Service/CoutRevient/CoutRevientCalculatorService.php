<?php

namespace App\Service\CoutRevient;

use App\Entity\CoutRevient;
use App\Entity\CoutRevientChargeLine;

final readonly class CoutRevientCalculatorService
{
    /**
     * Recalcule et ecrit tous les champs calcules.
     *
     * @return array<string, mixed>
     */
    public function calculate(CoutRevient $coutRevient): array
    {
        $poidsBrut = $this->num($coutRevient->getPoidsBrutRecu());
        $poidsProduction = $this->num($coutRevient->getPoidsMisEnProduction());
        $prixAchatKg = $this->num($coutRevient->getPrixAchatKg());
        $coutMatiere = ($poidsBrut * $prixAchatKg)
            + $this->num($coutRevient->getFraisTransportAchat())
            + $this->num($coutRevient->getAutresFraisAchat());

        $poidsFini = $this->num($coutRevient->getPoidsProduitFini());
        $rendement = $poidsProduction > 0 ? ($poidsFini / $poidsProduction) * 100 : 0.0;

        $coutMainOeuvre = match ($coutRevient->getModeCalculMainOeuvre()) {
            CoutRevient::MODE_HOUR => $coutRevient->getNombreOperatrices()
                * $this->num($coutRevient->getNombreHeures())
                * $this->num($coutRevient->getCoutHoraireMoyen()),
            CoutRevient::MODE_KG => $this->num($coutRevient->getPrixTacheKg())
                * $this->num($coutRevient->getKgTraitesMainOeuvre()),
            default => $this->num($coutRevient->getCoutMainOeuvreDirect()),
        };

        $coutEmballage = ($coutRevient->getNombreCartons() * $this->num($coutRevient->getPrixCarton()))
            + ($coutRevient->getNombreSachets() * $this->num($coutRevient->getPrixSachet()))
            + $this->num($coutRevient->getCoutEtiquettes())
            + $this->num($coutRevient->getCoutFilmPlastique())
            + $this->num($coutRevient->getAutresCoutEmballage());

        $coutCharges = 0.0;
        foreach ($coutRevient->getChargeLines() as $line) {
            $line->recalculate();
            $coutCharges += $this->num($line->getTotalAmount());
        }

        $coutCharges += $this->num($coutRevient->getCoutElectricite())
            + $this->num($coutRevient->getCoutEau())
            + $this->num($coutRevient->getCoutGlace())
            + $this->num($coutRevient->getCoutNettoyage())
            + $this->num($coutRevient->getCoutMaintenance())
            + $this->num($coutRevient->getCoutTransportLivraison())
            + $this->num($coutRevient->getAutresCharges());

        $coutTotal = $coutMatiere + $coutMainOeuvre + $coutEmballage + $coutCharges;
        $coutKg = $poidsFini > 0 ? $coutTotal / $poidsFini : 0.0;
        $prixVenteKg = $coutRevient->getPrixVenteKg() !== null ? $this->num($coutRevient->getPrixVenteKg()) : null;
        $margeKg = $prixVenteKg !== null && $prixVenteKg > 0 ? $prixVenteKg - $coutKg : 0.0;
        $margeTotale = $prixVenteKg !== null && $prixVenteKg > 0 ? $margeKg * $poidsFini : 0.0;
        $tauxMarge = $coutTotal > 0 ? ($margeTotale / $coutTotal) * 100 : 0.0;

        $coutRevient
            ->setCoutMatierePremiere($coutMatiere)
            ->setRendementPourcentage($rendement)
            ->setCoutMainOeuvre($coutMainOeuvre)
            ->setCoutEmballageTotal($coutEmballage)
            ->setCoutChargesTotal($coutCharges)
            ->setCoutTotalProduction($coutTotal)
            ->setCoutRevientKg($coutKg)
            ->setMargeKg($margeKg)
            ->setMargeTotale($margeTotale)
            ->setTauxMargePourcentage($tauxMarge);

        return $this->summary($coutRevient);
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    public function calculatePayload(array $payload): array
    {
        $coutRevient = new CoutRevient();
        $coutRevient
            ->setPoidsBrutRecu($payload['poidsBrutRecu'] ?? 0)
            ->setPoidsMisEnProduction($payload['poidsMisEnProduction'] ?? 0)
            ->setPrixAchatKg($payload['prixAchatKg'] ?? 0)
            ->setFraisTransportAchat($payload['fraisTransportAchat'] ?? 0)
            ->setAutresFraisAchat($payload['autresFraisAchat'] ?? 0)
            ->setPoidsProduitFini($payload['poidsProduitFini'] ?? 0)
            ->setPoidsDechets($payload['poidsDechets'] ?? 0)
            ->setPoidsPerte($payload['poidsPerte'] ?? 0)
            ->setModeCalculMainOeuvre((string) ($payload['modeCalculMainOeuvre'] ?? CoutRevient::MODE_DIRECT))
            ->setNombreOperatrices($payload['nombreOperatrices'] ?? 0)
            ->setNombreHeures($payload['nombreHeures'] ?? 0)
            ->setCoutHoraireMoyen($payload['coutHoraireMoyen'] ?? 0)
            ->setPrixTacheKg($payload['prixTacheKg'] ?? 0)
            ->setKgTraitesMainOeuvre($payload['kgTraitesMainOeuvre'] ?? 0)
            ->setCoutMainOeuvreDirect($payload['coutMainOeuvreDirect'] ?? 0)
            ->setNombreCartons($payload['nombreCartons'] ?? 0)
            ->setPrixCarton($payload['prixCarton'] ?? 0)
            ->setNombreSachets($payload['nombreSachets'] ?? 0)
            ->setPrixSachet($payload['prixSachet'] ?? 0)
            ->setCoutEtiquettes($payload['coutEtiquettes'] ?? 0)
            ->setCoutFilmPlastique($payload['coutFilmPlastique'] ?? 0)
            ->setAutresCoutEmballage($payload['autresCoutEmballage'] ?? 0)
            ->setCoutElectricite($payload['coutElectricite'] ?? 0)
            ->setCoutEau($payload['coutEau'] ?? 0)
            ->setCoutGlace($payload['coutGlace'] ?? 0)
            ->setCoutNettoyage($payload['coutNettoyage'] ?? 0)
            ->setCoutMaintenance($payload['coutMaintenance'] ?? 0)
            ->setCoutTransportLivraison($payload['coutTransportLivraison'] ?? 0)
            ->setAutresCharges($payload['autresCharges'] ?? 0)
            ->setPrixVenteKg($payload['prixVenteKg'] ?? null);

        $this->hydrateChargeLinesForPayload($coutRevient, is_array($payload['chargeLines'] ?? null) ? $payload['chargeLines'] : []);

        return $this->calculate($coutRevient);
    }

    public function assertValidatable(CoutRevient $coutRevient): void
    {
        $this->calculate($coutRevient);
        if ((float) $coutRevient->getPoidsProduitFini() <= 0) {
            throw new \DomainException('Impossible de valider sans poids produit fini.');
        }

        if ((float) $coutRevient->getRendementPourcentage() > 100) {
            throw new \DomainException('Rendement impossible : le poids fini depasse le poids mis en production.');
        }
    }

    /** @return array<string, mixed> */
    private function summary(CoutRevient $coutRevient): array
    {
        return [
            'coutMatierePremiere' => (float) $coutRevient->getCoutMatierePremiere(),
            'coutMainOeuvre' => (float) $coutRevient->getCoutMainOeuvre(),
            'coutEmballageTotal' => (float) $coutRevient->getCoutEmballageTotal(),
            'coutChargesTotal' => (float) $coutRevient->getCoutChargesTotal(),
            'coutTotalProduction' => (float) $coutRevient->getCoutTotalProduction(),
            'coutRevientKg' => (float) $coutRevient->getCoutRevientKg(),
            'rendementPourcentage' => (float) $coutRevient->getRendementPourcentage(),
            'margeKg' => (float) $coutRevient->getMargeKg(),
            'margeTotale' => (float) $coutRevient->getMargeTotale(),
            'tauxMargePourcentage' => (float) $coutRevient->getTauxMargePourcentage(),
            'rentabiliteLabel' => $coutRevient->getRentabiliteLabel(),
            'rentabiliteBadgeClass' => $coutRevient->getRentabiliteBadgeClass(),
            'alerts' => $coutRevient->getAlertMessages(),
        ];
    }

    /** @param array<int|string, mixed> $rows */
    private function hydrateChargeLinesForPayload(CoutRevient $coutRevient, array $rows): void
    {
        $sortOrder = 0;
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            if (!empty($row['remove'])) {
                continue;
            }

            $line = (new CoutRevientChargeLine())
                ->setName((string) ($row['name'] ?? ''))
                ->setCategory((string) ($row['category'] ?? ''))
                ->setCalculationUnit((string) ($row['calculationUnit'] ?? ''))
                ->setUnitCost($row['unitCost'] ?? 0)
                ->setQuantity($row['quantity'] ?? 0)
                ->setSortOrder(++$sortOrder)
                ->recalculate();

            if ($line->getName() === '' || (float) $line->getQuantity() <= 0) {
                continue;
            }

            $coutRevient->addChargeLine($line);
        }
    }

    private function num(int|float|string|null $value): float
    {
        $normalized = str_replace(',', '.', trim((string) ($value ?? '0')));

        return is_numeric($normalized) ? (float) $normalized : 0.0;
    }
}
