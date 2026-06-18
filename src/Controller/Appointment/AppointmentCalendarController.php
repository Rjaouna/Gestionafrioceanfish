<?php

namespace App\Controller\Appointment;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Security\Voter\ModuleAccessVoter;
use App\Service\Appointment\AppointmentService;
use App\Service\JsonResponder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/agenda')]
#[IsGranted('ROLE_USER')]
final class AppointmentCalendarController extends AbstractController
{
    public function __construct(
        private readonly AppointmentService $appointmentService,
        private readonly UserRepository $userRepository,
        private readonly JsonResponder $jsonResponder,
    ) {
    }

    #[Route('', name: 'app_appointment_calendar', methods: ['GET'])]
    #[Route('/calendrier', name: 'app_appointment_calendar_alias', methods: ['GET'])]
    public function calendar(): Response
    {
        $this->denyAccessUnlessGranted(ModuleAccessVoter::ACCESS, 'agenda');

        return $this->render('appointment/calendar.html.twig', [
            'stats' => $this->appointmentService->stats($this->currentUser()),
            'upcoming' => $this->appointmentService->upcoming($this->currentUser(), [], 8),
            'users' => $this->userRepository->findActiveUsers(),
            'mine' => false,
        ]);
    }

    #[Route('/mes-rendez-vous', name: 'app_appointment_my', methods: ['GET'])]
    public function myAppointments(): Response
    {
        $this->denyAccessUnlessGranted(ModuleAccessVoter::ACCESS, 'agenda');

        return $this->render('appointment/calendar.html.twig', [
            'stats' => $this->appointmentService->stats($this->currentUser(), true),
            'upcoming' => $this->appointmentService->upcoming($this->currentUser(), ['mine' => true], 8),
            'users' => $this->userRepository->findActiveUsers(),
            'mine' => true,
        ]);
    }

    #[Route('/events', name: 'app_appointment_events', methods: ['GET'])]
    public function events(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(ModuleAccessVoter::ACCESS, 'agenda');

        return new JsonResponse($this->appointmentService->events($this->currentUser(), $this->filters($request)));
    }

    #[Route('/users/search', name: 'app_appointment_user_search', methods: ['GET'])]
    public function searchUsers(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(ModuleAccessVoter::ACCESS, 'agenda');
        $query = mb_strtolower(trim((string) $request->query->get('q', '')));
        if (mb_strlen($query) < 2) {
            return $this->jsonResponder->success('Recherche prete.', ['users' => []]);
        }

        $users = array_values(array_filter($this->userRepository->findActiveUsers(), static function (User $user) use ($query): bool {
            return str_contains(mb_strtolower($user->getDisplayName().' '.$user->getEmail()), $query);
        }));

        return $this->jsonResponder->success('Utilisateurs trouvés.', [
            'users' => array_map(static fn (User $user): array => [
                'id' => $user->getId(),
                'name' => $user->getDisplayName(),
                'email' => $user->getEmail(),
            ], array_slice($users, 0, 12)),
        ]);
    }

    /** @return array<string, mixed> */
    private function filters(Request $request): array
    {
        return [
            'start' => (string) $request->query->get('start', ''),
            'end' => (string) $request->query->get('end', ''),
            'q' => (string) $request->query->get('q', ''),
            'status' => (string) $request->query->get('status', ''),
            'priority' => (string) $request->query->get('priority', ''),
            'appointmentType' => (string) $request->query->get('appointmentType', ''),
            'participant' => (string) $request->query->get('participant', ''),
            'mine' => $request->query->getBoolean('mine'),
        ];
    }

    private function currentUser(): User
    {
        $user = $this->getUser();
        \assert($user instanceof User);

        return $user;
    }
}
