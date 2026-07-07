<?php

namespace App\Controller;

use App\Entity\InterimPayment;
use App\Entity\User;
use App\Form\InterimPaymentType;
use App\Security\Voter\ModuleAccessVoter;
use App\Service\InterimPaymentService;
use App\Service\JsonResponder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/pointage-personnel/paiements')]
#[IsGranted('ROLE_USER')]
final class InterimPaymentController extends AbstractController
{
    public function __construct(
        private readonly InterimPaymentService $paymentService,
        private readonly JsonResponder $jsonResponder,
    ) {
    }

    #[Route('', name: 'app_interim_payment_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $this->denyAccessUnlessGranted(ModuleAccessVoter::ACCESS, 'pointage-personnel');
        $result = $this->paymentService->search($this->filtersFromRequest($request), max(1, $request->query->getInt('page', 1)));

        return $this->render('interim_payment/index.html.twig', [
            'items' => $result['items'],
            'pagination' => $result,
            'filters' => $result['filters'],
            'totals' => $result['totals'],
            'workers' => $this->paymentService->workerChoices(),
            'status_choices' => InterimPayment::STATUS_LABELS,
            'method_choices' => InterimPayment::METHOD_LABELS,
            'create_form' => $this->buildForm(new InterimPayment(), 'app_interim_payment_create'),
        ]);
    }

    #[Route('/nouveau', name: 'app_interim_payment_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(ModuleAccessVoter::ACCESS, 'pointage-personnel');
        $payment = new InterimPayment();
        $form = $this->buildForm($payment, 'app_interim_payment_create');
        $form->handleRequest($request);
        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->jsonResponder->invalidForm($form);
        }

        try {
            $this->paymentService->create($payment, $this->currentUser());
        } catch (\DomainException $exception) {
            return $this->jsonResponder->error($exception->getMessage(), [], 422);
        }

        return $this->jsonResponder->success('Paiement enregistre.', ['reload' => true], 201);
    }

    #[Route('/{id}/modifier', name: 'app_interim_payment_form', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function form(InterimPayment $payment): Response
    {
        $this->denyAccessUnlessGranted(ModuleAccessVoter::ACCESS, 'pointage-personnel');

        return $this->render('interim_payment/_form_modal.html.twig', [
            'payment' => $payment,
            'form' => $this->buildForm($payment, 'app_interim_payment_update', ['id' => $payment->getId()]),
            'title' => 'Modifier paiement',
            'submit_label' => 'Enregistrer',
        ]);
    }

    #[Route('/{id}/modifier', name: 'app_interim_payment_update', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function update(InterimPayment $payment, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(ModuleAccessVoter::ACCESS, 'pointage-personnel');
        $form = $this->buildForm($payment, 'app_interim_payment_update', ['id' => $payment->getId()]);
        $form->handleRequest($request);
        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->jsonResponder->invalidForm($form);
        }

        try {
            $this->paymentService->update($payment, $this->currentUser());
        } catch (\DomainException $exception) {
            return $this->jsonResponder->error($exception->getMessage(), [], 422);
        }

        return $this->jsonResponder->success('Paiement mis a jour.', ['reload' => true]);
    }

    /** @param array<string, int|string|null> $routeParameters */
    private function buildForm(InterimPayment $payment, string $route, array $routeParameters = []): FormInterface
    {
        return $this->createForm(InterimPaymentType::class, $payment, [
            'action' => $this->generateUrl($route, $routeParameters),
        ]);
    }

    /** @return array<string, mixed> */
    private function filtersFromRequest(Request $request): array
    {
        $defaultFrom = (new \DateTimeImmutable('first day of this month'))->format('Y-m-d');
        $defaultTo = (new \DateTimeImmutable('today'))->format('Y-m-d');

        return [
            'q' => trim((string) $request->query->get('q', '')),
            'workerId' => trim((string) $request->query->get('workerId', '')),
            'status' => trim((string) $request->query->get('status', '')),
            'paymentMethod' => trim((string) $request->query->get('paymentMethod', '')),
            'dateFrom' => trim((string) $request->query->get('dateFrom', $defaultFrom)),
            'dateTo' => trim((string) $request->query->get('dateTo', $defaultTo)),
            'periodFrom' => trim((string) $request->query->get('periodFrom', '')),
            'periodTo' => trim((string) $request->query->get('periodTo', '')),
        ];
    }

    private function currentUser(): User
    {
        $user = $this->getUser();
        \assert($user instanceof User);

        return $user;
    }
}
