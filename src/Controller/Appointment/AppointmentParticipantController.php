<?php

namespace App\Controller\Appointment;

use App\Entity\Appointment;
use App\Entity\AppointmentParticipant;
use App\Entity\User;
use App\Repository\AppointmentParticipantRepository;
use App\Repository\UserRepository;
use App\Security\Voter\AppointmentVoter;
use App\Service\Appointment\AppointmentParticipantService;
use App\Service\JsonResponder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/agenda/appointments/{id}/participants', requirements: ['id' => '\d+'])]
#[IsGranted('ROLE_USER')]
final class AppointmentParticipantController extends AbstractController
{
    public function __construct(
        private readonly AppointmentParticipantService $participantService,
        private readonly AppointmentParticipantRepository $participantRepository,
        private readonly UserRepository $userRepository,
        private readonly JsonResponder $jsonResponder,
    ) {
    }

    #[Route('/modal', name: 'app_appointment_participants_modal', methods: ['GET'])]
    public function modal(Appointment $appointment): Response
    {
        $this->denyAccessUnlessGranted(AppointmentVoter::ASSIGN_USER, $appointment);

        return $this->render('appointment/_participant_modal.html.twig', [
            'appointment' => $appointment,
            'users' => $this->userRepository->findActiveUsers(),
        ]);
    }

    #[Route('', name: 'app_appointment_participant_add', methods: ['POST'])]
    public function add(Appointment $appointment, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(AppointmentVoter::ASSIGN_USER, $appointment);
        $payload = $request->request->all() ?: $request->toArray();
        $this->assertCsrf((string) ($payload['token'] ?? ''), 'participant_appointment_'.$appointment->getId());

        $user = $this->userRepository->find((int) ($payload['userId'] ?? 0));
        if (!$user instanceof User || !$user->isActive()) {
            return $this->jsonResponder->error('Utilisateur introuvable.', [], 404);
        }

        try {
            $this->participantService->addParticipant(
                $appointment,
                $user,
                $this->currentUser(),
                (string) ($payload['roleInAppointment'] ?? 'participant'),
                'invited',
                filter_var($payload['isRequired'] ?? true, FILTER_VALIDATE_BOOL),
            );
        } catch (\DomainException $exception) {
            return $this->jsonResponder->error($exception->getMessage(), [], 422);
        }

        return $this->jsonResponder->success('Le participant a été ajouté.', ['reload' => true]);
    }

    #[Route('/{participantId}', name: 'app_appointment_participant_remove', requirements: ['participantId' => '\d+'], methods: ['DELETE'])]
    public function remove(Appointment $appointment, int $participantId, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(AppointmentVoter::ASSIGN_USER, $appointment);
        $payload = $request->toArray();
        $this->assertCsrf((string) ($payload['token'] ?? ''), 'participant_appointment_'.$appointment->getId());
        $this->participantService->removeParticipant($appointment, $participantId, $this->currentUser());

        return $this->jsonResponder->success('Le participant a été retiré.', ['reload' => true]);
    }

    #[Route('/{participantId}/response', name: 'app_appointment_participant_response', requirements: ['participantId' => '\d+'], methods: ['POST'])]
    public function response(Appointment $appointment, int $participantId, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(AppointmentVoter::VIEW, $appointment);
        $participant = $this->participantRepository->find($participantId);
        if (!$participant instanceof AppointmentParticipant || $participant->getAppointment()?->getId() !== $appointment->getId()) {
            return $this->jsonResponder->error('Participation introuvable.', [], 404);
        }

        $payload = $request->toArray();
        $this->assertCsrf((string) ($payload['token'] ?? ''), 'participant_response_'.$appointment->getId());
        $this->participantService->respond($participant, $this->currentUser(), (string) ($payload['response'] ?? ''));

        return $this->jsonResponder->success('Votre réponse a été enregistrée.', ['reload' => true]);
    }

    private function assertCsrf(string $token, string $id): void
    {
        if (!$this->isCsrfTokenValid($id, $token)) {
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
