<?php

namespace App\Controller\Appointment;

use App\Entity\Appointment;
use App\Entity\User;
use App\Form\AppointmentType;
use App\Repository\UserRepository;
use App\Security\Voter\AppointmentVoter;
use App\Security\Voter\ModuleAccessVoter;
use App\Service\Appointment\AppointmentHistoryService;
use App\Service\Appointment\AppointmentParticipantService;
use App\Service\Appointment\AppointmentService;
use App\Service\JsonResponder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/agenda/appointments')]
#[IsGranted('ROLE_USER')]
final class AppointmentController extends AbstractController
{
    public function __construct(
        private readonly AppointmentService $appointmentService,
        private readonly AppointmentParticipantService $participantService,
        private readonly AppointmentHistoryService $historyService,
        private readonly UserRepository $userRepository,
        private readonly JsonResponder $jsonResponder,
    ) {
    }

    #[Route('', name: 'app_appointment_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $this->denyAccessUnlessGranted(ModuleAccessVoter::ACCESS, 'agenda');
        $filters = $this->filters($request);
        $result = $this->appointmentService->search($this->currentUser(), $filters, max(1, $request->query->getInt('page', 1)));

        return $this->render('appointment/index.html.twig', [
            'appointments' => $result['items'],
            'pagination' => $result,
            'filters' => $filters,
            'users' => $this->userRepository->findActiveUsers(),
            'stats' => $this->appointmentService->stats($this->currentUser()),
            'create_form' => $this->buildForm($this->newAppointment(), 'app_appointment_create', selectedParticipants: [$this->currentUser()]),
        ]);
    }

    #[Route('/new', name: 'app_appointment_new', methods: ['GET'])]
    public function new(): Response
    {
        $this->denyAccessUnlessGranted(AppointmentVoter::CREATE);

        return $this->render('appointment/new.html.twig', [
            'form' => $this->buildForm($this->newAppointment(), 'app_appointment_create', selectedParticipants: [$this->currentUser()]),
        ]);
    }

    #[Route('/search', name: 'app_appointment_search', methods: ['GET'])]
    public function search(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(ModuleAccessVoter::ACCESS, 'agenda');
        $filters = $this->filters($request);
        $result = $this->appointmentService->search($this->currentUser(), $filters, max(1, $request->query->getInt('page', 1)));

        return $this->jsonResponder->success('Recherche mise à jour.', [
            'html' => $this->renderView('appointment/_appointment_grid.html.twig', [
                'appointments' => $result['items'],
                'pagination' => $result,
            ]),
            'count' => $result['total'],
            'page' => $result['page'],
            'pages' => $result['pages'],
        ]);
    }

    #[Route('/create', name: 'app_appointment_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(AppointmentVoter::CREATE);
        $appointment = $this->newAppointment(false);
        $form = $this->buildForm($appointment, 'app_appointment_create');
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->jsonResponder->invalidForm($form);
        }

        try {
            $this->appointmentService->create($appointment, $this->currentUser(), $this->participantsFromForm($form));
        } catch (\DomainException $exception) {
            return $this->jsonResponder->error($exception->getMessage(), [], 422);
        }

        return $this->jsonResponder->success('Le rendez-vous a été créé.', ['reload' => true], 201);
    }

    #[Route('/quick-create', name: 'app_appointment_quick_create', methods: ['POST'])]
    public function quickCreate(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(AppointmentVoter::CREATE);
        $payload = $request->toArray();
        $this->assertCalendarCsrf((string) ($payload['token'] ?? ''));

        try {
            $title = trim((string) ($payload['title'] ?? ''));
            if ($title === '') {
                throw new \DomainException('Le titre du rendez-vous est obligatoire.');
            }

            $startAt = new \DateTimeImmutable((string) ($payload['startAt'] ?? ''));
            $endAt = !empty($payload['endAt'])
                ? new \DateTimeImmutable((string) $payload['endAt'])
                : $startAt->modify('+'.max(15, (int) ($payload['duration'] ?? 60)).' minutes');

            $appointment = (new Appointment())
                ->setTitle($title)
                ->setStartAt($startAt)
                ->setEndAt($endAt)
                ->setLocation((string) ($payload['location'] ?? ''))
                ->setCustomerName((string) ($payload['customerName'] ?? ''))
                ->setAppointmentType((string) ($payload['appointmentType'] ?? 'client'))
                ->setPriority((string) ($payload['priority'] ?? 'normal'))
                ->setStatus('planned');

            $users = $this->participantService->usersFromIds((array) ($payload['participantIds'] ?? []));
            $this->appointmentService->create($appointment, $this->currentUser(), $users);
        } catch (\DomainException $exception) {
            return $this->jsonResponder->error($exception->getMessage(), [], 422);
        } catch (\Exception) {
            return $this->jsonResponder->error('Le créneau sélectionné est invalide.', [], 422);
        }

        return $this->jsonResponder->success('Le rendez-vous a été créé.', [
            'event' => $this->appointmentService->toCalendarEvent($appointment),
        ], 201);
    }

    #[Route('/{id}', name: 'app_appointment_view', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function view(Appointment $appointment, Request $request): Response
    {
        $this->denyAccessUnlessGranted(AppointmentVoter::VIEW, $appointment);
        $parameters = [
            'appointment' => $appointment,
            'history' => $this->historyService->getHistory($appointment),
        ];

        if (!$request->isXmlHttpRequest()) {
            return $this->render('appointment/show.html.twig', $parameters);
        }

        return $this->render('appointment/_details_modal.html.twig', $parameters);
    }

    #[Route('/{id}/form', name: 'app_appointment_form', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function form(Appointment $appointment): Response
    {
        $this->denyAccessUnlessGranted(AppointmentVoter::EDIT, $appointment);

        return $this->render('appointment/_form_modal.html.twig', [
            'form' => $this->buildForm($appointment, 'app_appointment_edit', ['id' => $appointment->getId()], $this->participantUsers($appointment)),
            'appointment' => $appointment,
            'title' => sprintf('Modifier %s', $appointment->getReference()),
            'submit_label' => 'Enregistrer',
        ]);
    }

    #[Route('/{id}/edit', name: 'app_appointment_edit', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function edit(Appointment $appointment, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(AppointmentVoter::EDIT, $appointment);
        $form = $this->buildForm($appointment, 'app_appointment_edit', ['id' => $appointment->getId()], $this->participantUsers($appointment));
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->jsonResponder->invalidForm($form);
        }

        try {
            $this->appointmentService->update($appointment, $this->currentUser(), $this->participantsFromForm($form));
        } catch (\DomainException $exception) {
            return $this->jsonResponder->error($exception->getMessage(), [], 422);
        }

        return $this->jsonResponder->success('Le rendez-vous a été modifié.', ['reload' => true]);
    }

    #[Route('/{id}/move', name: 'app_appointment_move', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function move(Appointment $appointment, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(AppointmentVoter::EDIT, $appointment);
        $payload = $request->toArray();
        $this->assertCalendarCsrf((string) ($payload['token'] ?? ''));

        try {
            $this->appointmentService->move($appointment, $this->currentUser(), new \DateTimeImmutable((string) ($payload['startAt'] ?? '')), new \DateTimeImmutable((string) ($payload['endAt'] ?? '')));
        } catch (\DomainException $exception) {
            return $this->jsonResponder->error($exception->getMessage(), [], 422);
        } catch (\Exception) {
            return $this->jsonResponder->error('Le nouveau créneau est invalide.', [], 422);
        }

        return $this->jsonResponder->success('Le rendez-vous a été déplacé.');
    }

    #[Route('/{id}/resize', name: 'app_appointment_resize', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function resize(Appointment $appointment, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(AppointmentVoter::EDIT, $appointment);
        $payload = $request->toArray();
        $this->assertCalendarCsrf((string) ($payload['token'] ?? ''));

        try {
            $this->appointmentService->resize($appointment, $this->currentUser(), new \DateTimeImmutable((string) ($payload['startAt'] ?? '')), new \DateTimeImmutable((string) ($payload['endAt'] ?? '')));
        } catch (\DomainException $exception) {
            return $this->jsonResponder->error($exception->getMessage(), [], 422);
        } catch (\Exception) {
            return $this->jsonResponder->error('La nouvelle durée est invalide.', [], 422);
        }

        return $this->jsonResponder->success('La durée du rendez-vous a été mise à jour.');
    }

    #[Route('/{id}/status', name: 'app_appointment_status', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function status(Appointment $appointment, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(AppointmentVoter::CHANGE_STATUS, $appointment);
        $payload = $request->toArray();
        $this->assertCalendarCsrf((string) ($payload['token'] ?? ''));

        try {
            $this->appointmentService->changeStatus($appointment, $this->currentUser(), (string) ($payload['status'] ?? ''), (string) ($payload['comment'] ?? ''));
        } catch (\DomainException $exception) {
            return $this->jsonResponder->error($exception->getMessage(), [], 422);
        }

        return $this->jsonResponder->success('Le statut a été mis à jour.', ['reload' => true]);
    }

    #[Route('/{id}/cancel', name: 'app_appointment_cancel', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function cancel(Appointment $appointment, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(AppointmentVoter::CANCEL, $appointment);
        $payload = $request->toArray();
        $this->assertCalendarCsrf((string) ($payload['token'] ?? ''));

        $this->appointmentService->cancel($appointment, $this->currentUser(), (string) ($payload['reason'] ?? ''));

        return $this->jsonResponder->success('Le rendez-vous a été annulé.', ['reload' => true]);
    }

    #[Route('/{id}', name: 'app_appointment_delete', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    public function delete(Appointment $appointment, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(AppointmentVoter::DELETE, $appointment);
        $payload = $request->toArray();
        $this->assertCalendarCsrf((string) ($payload['token'] ?? ''));

        $movedToTrash = $this->appointmentService->delete($appointment, $this->currentUser());

        return $this->jsonResponder->success($movedToTrash ? 'Le rendez-vous a été déplacé dans la corbeille.' : 'Le rendez-vous a été supprimé.', ['reload' => true]);
    }

    /** @param array<string, int|string|null> $parameters */
    private function buildForm(Appointment $appointment, string $route, array $parameters = [], array $selectedParticipants = []): FormInterface
    {
        return $this->createForm(AppointmentType::class, $appointment, [
            'action' => $this->generateUrl($route, $parameters),
            'selected_participants' => $selectedParticipants,
        ]);
    }

    private function newAppointment(bool $withDefaultSlot = true): Appointment
    {
        $appointment = new Appointment();
        if ($withDefaultSlot) {
            $startAt = (new \DateTimeImmutable())->modify('+1 hour')->setTime((int) (new \DateTimeImmutable())->modify('+1 hour')->format('H'), 0);
            $appointment
                ->setStartAt($startAt)
                ->setEndAt($startAt->modify('+1 hour'));
        }

        return $appointment;
    }

    /** @return list<User> */
    private function participantsFromForm(FormInterface $form): array
    {
        $participants = $form->get('participantUsers')->getData();
        if ($participants instanceof \Traversable) {
            return iterator_to_array($participants);
        }

        return is_array($participants) ? $participants : [];
    }

    /** @return list<User> */
    private function participantUsers(Appointment $appointment): array
    {
        $users = [];
        foreach ($appointment->getParticipants() as $participant) {
            if ($participant->isActive() && $participant->getUser() instanceof User) {
                $users[] = $participant->getUser();
            }
        }

        return $users;
    }

    /** @return array<string, mixed> */
    private function filters(Request $request): array
    {
        return [
            'q' => (string) $request->query->get('q', ''),
            'status' => (string) $request->query->get('status', ''),
            'priority' => (string) $request->query->get('priority', ''),
            'appointmentType' => (string) $request->query->get('appointmentType', ''),
            'participant' => (string) $request->query->get('participant', ''),
            'createdBy' => (string) $request->query->get('createdBy', ''),
            'dateFrom' => (string) $request->query->get('dateFrom', ''),
            'dateTo' => (string) $request->query->get('dateTo', ''),
            'active' => (string) $request->query->get('active', 'active'),
        ];
    }

    private function assertCalendarCsrf(string $token): void
    {
        if (!$this->isCsrfTokenValid('appointment_calendar', $token)) {
            throw new \DomainException('Jeton de sécurité invalide. Rechargez la page.');
        }
    }

    private function currentUser(): User
    {
        $user = $this->getUser();
        \assert($user instanceof User);

        return $user;
    }
}
