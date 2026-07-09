<?php

namespace App\Service;

use App\Entity\GeneratedContract;
use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Twig\Environment;

final readonly class GeneratedContractPdfService
{
    public function __construct(
        private Environment $twig,
        #[Autowire('%kernel.project_dir%/public')]
        private string $publicDir,
    ) {
    }

    public function generate(GeneratedContract $contract): string
    {
        $options = new Options();
        $options->setDefaultFont('DejaVu Sans');
        $options->setIsRemoteEnabled(false);
        $options->setChroot($this->publicDir);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($this->twig->render('generated_contract/pdf.html.twig', [
            'contract' => $contract,
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
            throw new \RuntimeException('Logo contrat introuvable.');
        }

        $content = file_get_contents($path);
        if ($content === false) {
            throw new \RuntimeException('Logo contrat illisible.');
        }

        return 'data:image/svg+xml;base64,'.base64_encode($content);
    }
}
