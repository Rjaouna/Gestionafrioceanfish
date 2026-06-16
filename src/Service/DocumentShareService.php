<?php

namespace App\Service;

use App\Entity\Document;
use App\Entity\DocumentShare;
use App\Entity\User;
use App\Repository\DocumentShareRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final readonly class DocumentShareService
{
    public function __construct(
        private DocumentShareRepository $shareRepository,
        private UserRepository $userRepository,
        private EntityManagerInterface $entityManager,
        private DocumentPermissionService $permission,
        private DocumentMailerService $mailer,
    ) {
    }

    /** @return list<DocumentShare> */
    public function getActiveShares(Document $document, User $actor): array
    {
        $this->assertCanShare($document, $actor);

        return $this->shareRepository->findActiveFor($document);
    }

    /** @return list<array{id: int, displayName: string, email: string, alreadyShared: bool}> */
    public function searchRecipients(Document $document, string $query, User $actor): array
    {
        $this->assertCanShare($document, $actor);
        $query = trim($query);
        if (mb_strlen($query) < 2) {
            return [];
        }

        $items = [];
        foreach ($this->userRepository->findActiveUsers() as $user) {
            if ($user === $actor) {
                continue;
            }

            $haystack = mb_strtolower($user->getDisplayName().' '.$user->getEmail());
            if (!str_contains($haystack, mb_strtolower($query))) {
                continue;
            }

            $items[] = [
                'id' => (int) $user->getId(),
                'displayName' => $user->getDisplayName(),
                'email' => (string) $user->getEmail(),
                'alreadyShared' => $this->shareRepository->findFor($document, $user)?->isActive() ?? false,
            ];

            if (count($items) >= 10) {
                break;
            }
        }

        return $items;
    }

    public function share(Document $document, int $userId, bool $canDownload, ?\DateTimeImmutable $expiresAt, User $actor): DocumentShare
    {
        $this->assertCanShare($document, $actor);
        $recipient = $this->userRepository->find($userId);
        if (!$recipient instanceof User || !$recipient->isActive()) {
            throw new \DomainException('Cet utilisateur ne peut pas recevoir ce document.');
        }

        $share = $this->shareRepository->findFor($document, $recipient);
        $isNew = !$share instanceof DocumentShare;
        $previous = null;
        if ($share instanceof DocumentShare) {
            $previous = [
                'canView' => $share->canView(),
                'canDownload' => $share->canDownload(),
                'expiresAt' => $share->getExpiresAt(),
                'isActive' => $share->isActive(),
                'emailSentAt' => $share->getEmailSentAt(),
            ];
        } else {
            $share = (new DocumentShare())
                ->setDocument($document)
                ->setUser($recipient)
                ->setCreatedBy($actor);
        }

        $share
            ->setCanView(true)
            ->setCanDownload($canDownload)
            ->setExpiresAt($expiresAt)
            ->setIsActive(true);
        $this->entityManager->persist($share);
        $this->entityManager->flush();

        try {
            $this->mailer->sendShareNotification($share, $actor);
        } catch (TransportExceptionInterface $exception) {
            if ($isNew) {
                $this->entityManager->remove($share);
            } elseif ($previous) {
                $share
                    ->setCanView($previous['canView'])
                    ->setCanDownload($previous['canDownload'])
                    ->setExpiresAt($previous['expiresAt'])
                    ->setIsActive($previous['isActive'])
                    ->setEmailSentAt($previous['emailSentAt']);
            }
            $this->entityManager->flush();

            throw $exception;
        }

        $share->setEmailSentAt(new \DateTimeImmutable());
        $this->entityManager->flush();

        return $share;
    }

    public function remove(DocumentShare $share, User $actor): void
    {
        if (!$this->permission->canManageShare($actor, $share)) {
            throw new AccessDeniedException();
        }

        $share->setIsActive(false);
        $this->entityManager->flush();
    }

    private function assertCanShare(Document $document, User $actor): void
    {
        if (!$this->permission->canShare($actor, $document)) {
            throw new AccessDeniedException();
        }
    }
}
