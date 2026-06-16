<?php

namespace App\Security\Voter;

use App\Entity\Document;
use App\Entity\User;
use App\Service\DocumentPermissionService;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

final class DocumentVoter extends Voter
{
    public const VIEW = 'DOCUMENT_VIEW';
    public const CREATE = 'DOCUMENT_CREATE';
    public const EDIT = 'DOCUMENT_EDIT';
    public const SHARE = 'DOCUMENT_SHARE';
    public const EMAIL = 'DOCUMENT_EMAIL';
    public const DOWNLOAD = 'DOCUMENT_DOWNLOAD';
    public const ARCHIVE = 'DOCUMENT_ARCHIVE';
    public const DELETE = 'DOCUMENT_DELETE';

    public function __construct(private readonly DocumentPermissionService $permission)
    {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::VIEW, self::CREATE, self::EDIT, self::SHARE, self::EMAIL, self::DOWNLOAD, self::ARCHIVE, self::DELETE], true)
            && ($subject === null || $subject instanceof Document);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        return match ($attribute) {
            self::CREATE => $this->permission->canCreate($user),
            self::VIEW => $subject instanceof Document && $this->permission->canView($user, $subject),
            self::EDIT => $subject instanceof Document && $this->permission->canEdit($user, $subject),
            self::SHARE => $subject instanceof Document && $this->permission->canShare($user, $subject),
            self::EMAIL => $subject instanceof Document && $this->permission->canEmail($user, $subject),
            self::DOWNLOAD => $subject instanceof Document && $this->permission->canDownload($user, $subject),
            self::ARCHIVE => $subject instanceof Document && $this->permission->canArchive($user, $subject),
            self::DELETE => $subject instanceof Document && $this->permission->canDelete($user, $subject),
            default => false,
        };
    }
}
