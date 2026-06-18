<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Security\Voter\TrashVoter;
use App\Service\JsonResponder;
use App\Service\Trash\TrashService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/corbeille')]
#[IsGranted('ROLE_SUPER_ADMIN')]
final class TrashController extends AbstractController
{
    public function __construct(
        private readonly TrashService $trashService,
        private readonly UserRepository $userRepository,
        private readonly JsonResponder $jsonResponder,
    ) {
    }

    #[Route('', name: 'app_trash_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $this->denyAccessUnlessGranted(TrashVoter::VIEW);
        $filters = [
            'type' => trim((string) $request->query->get('type', '')),
            'module' => trim((string) $request->query->get('module', '')),
            'deletedBy' => trim((string) $request->query->get('deletedBy', '')),
            'dateFrom' => trim((string) $request->query->get('dateFrom', '')),
            'dateTo' => trim((string) $request->query->get('dateTo', '')),
        ];
        $query = trim((string) $request->query->get('q', ''));

        return $this->render('trash/index.html.twig', [
            'items' => $this->trashService->findDeletedItems($query, $filters),
            'trashables' => $this->trashService->getTrashableEntities(),
            'modules' => array_values(array_unique(array_column($this->trashService->getTrashableEntities(), 'module'))),
            'deleted_users' => $this->userRepository->findBy([], ['lastName' => 'ASC', 'firstName' => 'ASC', 'email' => 'ASC']),
            'filters' => $filters,
            'query' => $query,
        ]);
    }

    #[Route('/{type}/{id}/restaurer', name: 'app_trash_restore', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function restore(string $type, int $id, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(TrashVoter::RESTORE);
        $payload = $request->toArray();
        $this->assertCsrf((string) ($payload['token'] ?? ''), 'restore_trash_'.$type.'_'.$id);
        $this->trashService->restore($this->trashService->findTrashItem($type, $id), $this->currentUser());

        return $this->jsonResponder->success('L’élément a été restauré avec succès.', ['reload' => true]);
    }

    #[Route('/{type}/{id}', name: 'app_trash_delete_permanently', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    public function deletePermanently(string $type, int $id, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(TrashVoter::DELETE_PERMANENTLY);
        $payload = $request->toArray();
        $this->assertCsrf((string) ($payload['token'] ?? ''), 'delete_trash_'.$type.'_'.$id);
        $this->trashService->deletePermanently($this->trashService->findTrashItem($type, $id), $this->currentUser());

        return $this->jsonResponder->success('L’élément a été supprimé définitivement.', ['reload' => true]);
    }

    private function currentUser(): User
    {
        $user = $this->getUser();
        \assert($user instanceof User);

        return $user;
    }

    private function assertCsrf(string $token, string $id): void
    {
        if (!$this->isCsrfTokenValid($id, $token)) {
            throw new \DomainException('Jeton de sécurité invalide. Rechargez la page.');
        }
    }
}
