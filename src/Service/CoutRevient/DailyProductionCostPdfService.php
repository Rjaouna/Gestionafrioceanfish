<?php

namespace App\Service\CoutRevient;

use App\Entity\DailyProductionCost;
use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Twig\Environment;

final readonly class DailyProductionCostPdfService
{
    public function __construct(
        private Environment $twig,
        private DailyProductionCostCalculatorService $calculator,
        #[Autowire('%kernel.project_dir%/public')]
        private string $publicDir,
    ) {
    }

    public function generate(DailyProductionCost $cost): string
    {
        $this->calculator->calculate($cost);

        $options = new Options();
        $options->setDefaultFont('DejaVu Sans');
        $options->setIsRemoteEnabled(false);
        $options->setChroot($this->publicDir);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($this->twig->render('daily_production_cost/pdf.html.twig', [
            'item' => $cost,
            'charts' => $this->calculator->chartData($cost),
            'logo_data_uri' => $this->logoDataUri(),
        ]), 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }

    private function logoDataUri(): string
    {
        $path = $this->publicDir.'/images/logo-afriocean-fish-navy.svg';
        if (!is_file($path)) {
            $path = $this->publicDir.'/images/logo-afriocean-fish.svg';
        }

        if (!is_file($path)) {
            return '';
        }

        $content = file_get_contents($path);
        if ($content === false) {
            return '';
        }

        return 'data:image/svg+xml;base64,'.base64_encode($content);
    }
}
