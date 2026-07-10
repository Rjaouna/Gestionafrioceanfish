<?php

namespace App\Controller;

use App\Entity\InterimAttendance;
use App\Entity\InterimAttendanceRate;
use App\Entity\InterimWorker;
use App\Entity\User;
use App\Form\InterimAttendanceHourlyType;
use App\Form\InterimAttendanceRateType;
use App\Form\InterimAttendanceTaskType;
use App\Security\Voter\InterimWorkerVoter;
use App\Security\Voter\ModuleAccessVoter;
use App\Service\InterimAttendanceService;
use App\Service\JsonResponder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/pointage-personnel')]
#[IsGranted('ROLE_USER')]
final class InterimAttendanceController extends AbstractController
{
    public function __construct(
        private readonly InterimAttendanceService $attendanceService,
        private readonly JsonResponder $jsonResponder,
    ) {
    }

    #[Route('', name: 'app_interim_attendance_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $this->denyAccessUnlessGranted(ModuleAccessVoter::ACCESS, 'pointage-personnel');
        $result = $this->attendanceService->search($this->filtersFromRequest($request), max(1, $request->query->getInt('page', 1)));

        return $this->render('interim_attendance/index.html.twig', [
            'items' => $result['items'],
            'pagination' => $result,
            'filters' => $result['filters'],
            'totals' => $result['totals'],
        ]);
    }

    #[Route('/details', name: 'app_interim_attendance_details', methods: ['GET'])]
    public function details(Request $request): Response
    {
        $this->denyAccessUnlessGranted(ModuleAccessVoter::ACCESS, 'pointage-personnel');
        $data = $this->attendanceService->details($this->filtersFromRequest($request));

        return $this->render('interim_attendance/details.html.twig', $data);
    }

    #[Route('/journal', name: 'app_interim_attendance_journal', methods: ['GET'])]
    public function journal(Request $request): Response
    {
        $this->denyAccessUnlessGranted(ModuleAccessVoter::ACCESS, 'pointage-personnel');

        return $this->render('interim_attendance/journal.html.twig', $this->attendanceService->journal($this->journalFiltersFromRequest($request)));
    }

    #[Route('/journal/pdf', name: 'app_interim_attendance_journal_pdf', methods: ['GET'])]
    public function journalPdf(Request $request): Response
    {
        $this->denyAccessUnlessGranted(ModuleAccessVoter::ACCESS, 'pointage-personnel');
        $data = $this->attendanceService->journal($this->journalFiltersFromRequest($request));
        $data['generated_at'] = new \DateTimeImmutable();
        $data['generated_by'] = $this->currentUser()->getDisplayName();

        return $this->render('interim_attendance/journal_pdf.html.twig', $data);
    }

    #[Route('/feuille-pointage', name: 'app_interim_attendance_sheet', methods: ['GET'])]
    public function sheet(Request $request): Response
    {
        $this->denyAccessUnlessGranted(ModuleAccessVoter::ACCESS, 'pointage-personnel');
        $data = $this->attendanceService->attendanceSheet($this->sheetFiltersFromRequest($request));
        $data['generated_by'] = $this->currentUser()->getDisplayName();

        return $this->render('interim_attendance/sheet.html.twig', $data);
    }

    #[Route('/recherche', name: 'app_interim_attendance_search', methods: ['GET'])]
    public function search(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(ModuleAccessVoter::ACCESS, 'pointage-personnel');
        $result = $this->attendanceService->search($this->filtersFromRequest($request), max(1, $request->query->getInt('page', 1)));

        return $this->jsonResponder->success('Pointage mis a jour.', [
            'html' => $this->renderView('interim_attendance/_grid.html.twig', [
                'items' => $result['items'],
                'pagination' => $result,
            ]),
            'count' => $result['total'],
            'page' => $result['page'],
            'pages' => $result['pages'],
        ]);
    }

    #[Route('/interimaires/{id}/choix', name: 'app_interim_attendance_choice', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function choice(InterimWorker $worker): Response
    {
        $this->denyAccessUnlessGranted(ModuleAccessVoter::ACCESS, 'pointage-personnel');
        $this->denyAccessUnlessGranted(InterimWorkerVoter::EDIT, $worker);

        return $this->render('interim_attendance/_choice_modal.html.twig', [
            'worker' => $worker,
        ]);
    }

    #[Route('/interimaires/{id}/heure/formulaire', name: 'app_interim_attendance_hourly_form', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function hourlyForm(InterimWorker $worker, Request $request): Response
    {
        $this->denyAccessUnlessGranted(ModuleAccessVoter::ACCESS, 'pointage-personnel');
        $this->denyAccessUnlessGranted(InterimWorkerVoter::EDIT, $worker);
        $attendance = $this->attendanceService->hourlyDraft($worker, $this->dateQuery($request));

        return $this->render('interim_attendance/_hourly_form_modal.html.twig', [
            'worker' => $worker,
            'attendance' => $attendance,
            'form' => $this->buildHourlyForm($worker, $attendance),
        ]);
    }

    #[Route('/interimaires/{id}/heure', name: 'app_interim_attendance_hourly_save', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function saveHourly(InterimWorker $worker, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(ModuleAccessVoter::ACCESS, 'pointage-personnel');
        $this->denyAccessUnlessGranted(InterimWorkerVoter::EDIT, $worker);
        $attendance = $this->attendanceService->hourlyDraft($worker);
        $form = $this->buildHourlyForm($worker, $attendance);
        $form->handleRequest($request);
        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->jsonResponder->invalidForm($form);
        }

        try {
            $this->attendanceService->saveHourly($worker, $attendance, $this->currentUser());
        } catch (\DomainException $exception) {
            return $this->jsonResponder->error($exception->getMessage(), [], 422);
        }

        return $this->jsonResponder->success('Pointage horaire enregistre.', ['reload' => true]);
    }

    #[Route('/interimaires/{id}/tache/formulaire', name: 'app_interim_attendance_task_form', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function taskForm(InterimWorker $worker, Request $request): Response
    {
        $this->denyAccessUnlessGranted(ModuleAccessVoter::ACCESS, 'pointage-personnel');
        $this->denyAccessUnlessGranted(InterimWorkerVoter::EDIT, $worker);
        $attendance = $this->attendanceService->taskDraft($worker, $this->dateQuery($request));

        return $this->render('interim_attendance/_task_form_modal.html.twig', [
            'worker' => $worker,
            'attendance' => $attendance,
            'form' => $this->buildTaskForm($worker, $attendance),
        ]);
    }

    #[Route('/interimaires/{id}/tache', name: 'app_interim_attendance_task_save', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function saveTask(InterimWorker $worker, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(ModuleAccessVoter::ACCESS, 'pointage-personnel');
        $this->denyAccessUnlessGranted(InterimWorkerVoter::EDIT, $worker);
        $attendance = $this->attendanceService->taskDraft($worker);
        $form = $this->buildTaskForm($worker, $attendance);
        $form->handleRequest($request);
        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->jsonResponder->invalidForm($form);
        }

        try {
            $this->attendanceService->saveTask($worker, $attendance, $this->currentUser());
        } catch (\DomainException $exception) {
            return $this->jsonResponder->error($exception->getMessage(), [], 422);
        }

        return $this->jsonResponder->success('Pointage a la tache enregistre.', ['reload' => true]);
    }

    #[Route('/interimaires/{id}/historique', name: 'app_interim_attendance_worker_history', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function workerHistory(InterimWorker $worker, Request $request): Response
    {
        $this->denyAccessUnlessGranted(ModuleAccessVoter::ACCESS, 'pointage-personnel');
        $this->denyAccessUnlessGranted(InterimWorkerVoter::VIEW, $worker);
        $month = trim((string) $request->query->get('month', ''));
        $data = $this->attendanceService->workerMonth($worker, $month);
        $current = new \DateTimeImmutable($data['month'].'-01');

        return $this->render('interim_attendance/_history_modal.html.twig', [
            'worker' => $worker,
            'items' => $data['items'],
            'totals' => $data['totals'],
            'month' => $data['month'],
            'previous_month' => $current->modify('-1 month')->format('Y-m'),
            'next_month' => $current->modify('+1 month')->format('Y-m'),
        ]);
    }

    #[Route('/journal/interimaires/{id}/dates', name: 'app_interim_attendance_journal_date_modal', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function journalDateModal(InterimWorker $worker, Request $request): Response
    {
        $this->denyAccessUnlessGranted(ModuleAccessVoter::ACCESS, 'pointage-personnel');
        $this->denyAccessUnlessGranted(InterimWorkerVoter::EDIT, $worker);

        return $this->render('interim_attendance/_journal_date_modal.html.twig', $this->attendanceService->journalDateCorrection($worker, $this->journalFiltersFromRequest($request)));
    }

    #[Route('/pointages/{id}/date', name: 'app_interim_attendance_update_date', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function updateDate(InterimAttendance $attendance, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(ModuleAccessVoter::ACCESS, 'pointage-personnel');
        $worker = $attendance->getWorker();
        if (!$worker instanceof InterimWorker) {
            return $this->jsonResponder->error('Interimaire introuvable.', [], 404);
        }
        $this->denyAccessUnlessGranted(InterimWorkerVoter::EDIT, $worker);

        if (!$this->isCsrfTokenValid('update_attendance_date_'.$attendance->getId(), (string) $request->request->get('_token', ''))) {
            return $this->jsonResponder->error('Jeton de securite invalide. Rechargez la page.', [], 422);
        }

        $date = trim((string) $request->request->get('attendanceDate', ''));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return $this->jsonResponder->error('Date de pointage invalide.', [], 422);
        }

        try {
            $this->attendanceService->updateAttendanceDate($attendance, new \DateTimeImmutable($date), $this->currentUser());
        } catch (\DomainException $exception) {
            return $this->jsonResponder->error($exception->getMessage(), [], 422);
        }

        return $this->jsonResponder->success('Date de pointage modifiee.', ['reload' => true]);
    }

    #[Route('/tarifs', name: 'app_interim_attendance_rate_index', methods: ['GET'])]
    public function rates(): Response
    {
        $this->denyAccessUnlessGranted(ModuleAccessVoter::ACCESS, 'pointage-personnel');

        return $this->render('interim_attendance/rates.html.twig', [
            'rates' => $this->attendanceService->rates(),
        ]);
    }

    #[Route('/tarifs/{id}/formulaire', name: 'app_interim_attendance_rate_form', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function rateForm(InterimAttendanceRate $rate): Response
    {
        $this->denyAccessUnlessGranted(ModuleAccessVoter::ACCESS, 'pointage-personnel');

        return $this->render('interim_attendance/_rate_form_modal.html.twig', [
            'rate' => $rate,
            'form' => $this->buildRateForm($rate),
        ]);
    }

    #[Route('/tarifs/{id}/modifier', name: 'app_interim_attendance_rate_update', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function updateRate(InterimAttendanceRate $rate, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(ModuleAccessVoter::ACCESS, 'pointage-personnel');
        $form = $this->buildRateForm($rate);
        $form->handleRequest($request);
        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->jsonResponder->invalidForm($form);
        }

        $this->attendanceService->updateRate($rate, $this->currentUser());

        return $this->jsonResponder->success('Tarif mis a jour.', ['reload' => true]);
    }

    private function buildHourlyForm(InterimWorker $worker, InterimAttendance $attendance): FormInterface
    {
        return $this->createForm(InterimAttendanceHourlyType::class, $attendance, [
            'action' => $this->generateUrl('app_interim_attendance_hourly_save', ['id' => $worker->getId()]),
            'attr' => ['data-interim-attendance-hourly-form' => 'true'],
        ]);
    }

    private function buildTaskForm(InterimWorker $worker, InterimAttendance $attendance): FormInterface
    {
        return $this->createForm(InterimAttendanceTaskType::class, $attendance, [
            'action' => $this->generateUrl('app_interim_attendance_task_save', ['id' => $worker->getId()]),
            'attr' => [
                'data-interim-attendance-task-form' => 'true',
                'data-cleaning-rate' => (string) $this->attendanceService->defaultTaskRate(InterimAttendanceRate::CODE_TASK_CLEANING),
                'data-boxing-rate' => (string) $this->attendanceService->defaultTaskRate(InterimAttendanceRate::CODE_TASK_BOXING),
                'data-cleaning-box-kg' => (string) InterimAttendance::CLEANING_BOX_WEIGHT_KG,
                'data-cleaning-rate-kg' => (string) InterimAttendance::CLEANING_RATE_WEIGHT_KG,
            ],
        ]);
    }

    private function buildRateForm(InterimAttendanceRate $rate): FormInterface
    {
        return $this->createForm(InterimAttendanceRateType::class, $rate, [
            'action' => $this->generateUrl('app_interim_attendance_rate_update', ['id' => $rate->getId()]),
        ]);
    }

    /** @return array<string, mixed> */
    private function filtersFromRequest(Request $request): array
    {
        $defaultFrom = (new \DateTimeImmutable('first day of this month'))->format('Y-m-d');
        $defaultTo = (new \DateTimeImmutable('today'))->format('Y-m-d');

        return [
            'q' => trim((string) $request->query->get('q', '')),
            'mode' => trim((string) $request->query->get('mode', '')),
            'dateFrom' => trim((string) $request->query->get('dateFrom', $defaultFrom)),
            'dateTo' => trim((string) $request->query->get('dateTo', $defaultTo)),
        ];
    }

    /** @return array<string, string> */
    private function journalFiltersFromRequest(Request $request): array
    {
        $today = (new \DateTimeImmutable('today'))->format('Y-m-d');

        return [
            'dateFrom' => trim((string) $request->query->get('dateFrom', $today)),
            'dateTo' => trim((string) $request->query->get('dateTo', $today)),
        ];
    }

    /** @return array<string, string> */
    private function sheetFiltersFromRequest(Request $request): array
    {
        return [
            'date' => trim((string) $request->query->get('date', (new \DateTimeImmutable('today'))->format('Y-m-d'))),
        ];
    }

    private function dateQuery(Request $request): ?\DateTimeImmutable
    {
        $date = trim((string) $request->query->get('date', ''));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return null;
        }

        return new \DateTimeImmutable($date);
    }

    private function currentUser(): User
    {
        $user = $this->getUser();
        \assert($user instanceof User);

        return $user;
    }
}
