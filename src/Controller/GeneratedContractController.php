<?php

namespace App\Controller;

use App\Entity\GeneratedContract;
use App\Entity\User;
use App\Form\GeneratedContractType;
use App\Security\Voter\ModuleAccessVoter;
use App\Service\GeneratedContractPdfService;
use App\Service\GeneratedContractService;
use App\Service\JsonResponder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/contrats')]
#[IsGranted('ROLE_USER')]
final class GeneratedContractController extends AbstractController
{
    private const MODULE_SLUG = 'contracts';

    public function __construct(
        private readonly GeneratedContractService $contractService,
        private readonly GeneratedContractPdfService $pdfService,
        private readonly JsonResponder $jsonResponder,
    ) {
    }

    #[Route('', name: 'app_generated_contract_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $this->denyModuleAccess();
        $result = $this->contractService->search($this->filtersFromRequest($request), $request->query->getInt('page', 1));

        return $this->render('generated_contract/index.html.twig', [
            'contracts' => $result['items'],
            'pagination' => $result,
            'filters' => $result['filters'],
            'types' => GeneratedContract::TYPE_LABELS,
            'statuses' => GeneratedContract::STATUS_LABELS,
        ]);
    }

    #[Route('/conditionnement/nouveau', name: 'app_generated_contract_conditioning_new', methods: ['GET'])]
    public function newConditioning(): Response
    {
        $this->denyModuleAccess();
        $contract = (new GeneratedContract())->setContractType(GeneratedContract::TYPE_CONDITIONING);

        return $this->render('generated_contract/new.html.twig', [
            'form' => $this->buildForm($contract, 'app_generated_contract_conditioning_create'),
            'contract' => $contract,
            'title' => 'Nouveau contrat de conditionnement',
            'submit_label' => 'Enregistrer et preparer le PDF',
        ]);
    }

    #[Route('/conditionnement/creer', name: 'app_generated_contract_conditioning_create', methods: ['POST'])]
    public function createConditioning(Request $request): Response
    {
        $this->denyModuleAccess();
        $contract = (new GeneratedContract())->setContractType(GeneratedContract::TYPE_CONDITIONING);
        $form = $this->buildForm($contract, 'app_generated_contract_conditioning_create');
        $form->handleRequest($request);
        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->render('generated_contract/new.html.twig', [
                'form' => $form,
                'contract' => $contract,
                'title' => 'Nouveau contrat de conditionnement',
                'submit_label' => 'Enregistrer et preparer le PDF',
            ], new Response(status: 422));
        }

        $this->contractService->create($contract, $this->currentUser());
        $this->addFlash('success', 'Contrat enregistre. Verifiez l apercu avant de telecharger le PDF.');

        return $this->redirectToRoute('app_generated_contract_show', ['id' => $contract->getId()]);
    }

    #[Route('/{id}/voir', name: 'app_generated_contract_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(GeneratedContract $contract): Response
    {
        $this->denyModuleAccess();

        return $this->render('generated_contract/show.html.twig', ['contract' => $contract]);
    }

    #[Route('/{id}/modifier', name: 'app_generated_contract_edit', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function edit(GeneratedContract $contract): Response
    {
        $this->denyModuleAccess();

        return $this->render('generated_contract/edit.html.twig', [
            'form' => $this->buildForm($contract, 'app_generated_contract_update', ['id' => $contract->getId()]),
            'contract' => $contract,
            'title' => sprintf('Modifier %s', $contract->getReference()),
            'submit_label' => 'Enregistrer les modifications',
        ]);
    }

    #[Route('/{id}/modifier', name: 'app_generated_contract_update', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function update(GeneratedContract $contract, Request $request): Response
    {
        $this->denyModuleAccess();
        $form = $this->buildForm($contract, 'app_generated_contract_update', ['id' => $contract->getId()]);
        $form->handleRequest($request);
        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->render('generated_contract/edit.html.twig', [
                'form' => $form,
                'contract' => $contract,
                'title' => sprintf('Modifier %s', $contract->getReference()),
                'submit_label' => 'Enregistrer les modifications',
            ], new Response(status: 422));
        }

        $this->contractService->update($contract, $this->currentUser());
        $this->addFlash('success', 'Contrat modifie. Un nouveau PDF doit etre genere.');

        return $this->redirectToRoute('app_generated_contract_show', ['id' => $contract->getId()]);
    }

    #[Route('/{id}/apercu.pdf', name: 'app_generated_contract_preview', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function preview(GeneratedContract $contract): Response
    {
        $this->denyModuleAccess();

        return $this->pdfResponse($contract, ResponseHeaderBag::DISPOSITION_INLINE);
    }

    #[Route('/{id}/telecharger.pdf', name: 'app_generated_contract_download', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function download(GeneratedContract $contract): Response
    {
        $this->denyModuleAccess();
        $pdf = $this->pdfService->generate($contract);
        $this->contractService->markGenerated($contract, $this->currentUser());

        return $this->pdfResponse($contract, ResponseHeaderBag::DISPOSITION_ATTACHMENT, $pdf);
    }

    #[Route('/{id}/supprimer', name: 'app_generated_contract_delete', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    public function delete(GeneratedContract $contract, Request $request): JsonResponse
    {
        $this->denyModuleAccess();
        $payload = $request->toArray();
        if (!$this->isCsrfTokenValid('delete_generated_contract_'.$contract->getId(), (string) ($payload['token'] ?? ''))) {
            return $this->jsonResponder->error('Jeton de securite invalide. Rechargez la page.', [], 422);
        }

        $this->contractService->delete($contract, $this->currentUser());

        return $this->jsonResponder->success('Contrat supprime.', [
            'redirectUrl' => $this->generateUrl('app_generated_contract_index'),
        ]);
    }

    private function pdfResponse(GeneratedContract $contract, string $disposition, ?string $pdf = null): Response
    {
        $response = new Response($pdf ?? $this->pdfService->generate($contract));
        $response->headers->set('Content-Type', 'application/pdf');
        $response->headers->set('Content-Disposition', HeaderUtils::makeDisposition(
            $disposition,
            $this->contractService->fileName($contract),
        ));
        $response->headers->set('Cache-Control', 'private, no-store, max-age=0');

        return $response;
    }

    /** @param array<string, int|string|null> $routeParameters */
    private function buildForm(GeneratedContract $contract, string $route, array $routeParameters = []): FormInterface
    {
        return $this->createForm(GeneratedContractType::class, $contract, [
            'action' => $this->generateUrl($route, $routeParameters),
        ]);
    }

    /** @return array<string, string> */
    private function filtersFromRequest(Request $request): array
    {
        return [
            'q' => trim((string) $request->query->get('q', '')),
            'type' => trim((string) $request->query->get('type', '')),
            'status' => trim((string) $request->query->get('status', '')),
            'dateFrom' => trim((string) $request->query->get('dateFrom', '')),
            'dateTo' => trim((string) $request->query->get('dateTo', '')),
        ];
    }

    private function denyModuleAccess(): void
    {
        $this->denyAccessUnlessGranted(ModuleAccessVoter::ACCESS, self::MODULE_SLUG);
    }

    private function currentUser(): User
    {
        $user = $this->getUser();
        \assert($user instanceof User);

        return $user;
    }
}
