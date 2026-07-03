<?php

namespace App\Security\Voter;

use App\Entity\InterimWorker;
use App\Entity\InterimWorkerDocument;
use App\Entity\User;
use App\Service\InterimWorkerPermissionService;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

final class InterimWorkerVoter extends Voter
{
    public const VIEW = 'INTERIM_WORKER_VIEW';
    public const CREATE = 'INTERIM_WORKER_CREATE';
    public const EDIT = 'INTERIM_WORKER_EDIT';
    public const DELETE = 'INTERIM_WORKER_DELETE';
    public const PRINT = 'INTERIM_WORKER_PRINT';
    public const DOWNLOAD_DOCUMENT = 'INTERIM_WORKER_DOWNLOAD_DOCUMENT';

    public function __construct(private readonly InterimWorkerPermissionService $permission)
    {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [
            self::VIEW,
            self::CREATE,
            self::EDIT,
            self::DELETE,
            self::PRINT,
            self::DOWNLOAD_DOCUMENT,
        ], true) && ($subject === null || $subject instanceof InterimWorker || $subject instanceof InterimWorkerDocument);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        return match ($attribute) {
            self::CREATE => $this->permission->canCreate($user),
            self::VIEW => $subject instanceof InterimWorker && $this->permission->canView($user, $subject),
            self::EDIT => $subject instanceof InterimWorker && $this->permission->canEdit($user, $subject),
            self::DELETE => $subject instanceof InterimWorker && $this->permission->canDelete($user, $subject),
            self::PRINT => $subject instanceof InterimWorker && $this->permission->canView($user, $subject),
            self::DOWNLOAD_DOCUMENT => $subject instanceof InterimWorkerDocument && $this->permission->canDownloadDocument($user, $subject),
            default => false,
        };
    }
}
