<?php

namespace App\Security\Voter;

use App\Entity\Contact;
use App\Entity\User;
use App\Service\ContactPermissionService;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

final class ContactVoter extends Voter
{
    public const VIEW = 'CONTACT_VIEW';
    public const CREATE = 'CONTACT_CREATE';
    public const EDIT = 'CONTACT_EDIT';
    public const SHARE = 'CONTACT_SHARE';
    public const DELETE = 'CONTACT_DELETE';

    public function __construct(private readonly ContactPermissionService $permission)
    {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::VIEW, self::CREATE, self::EDIT, self::SHARE, self::DELETE], true)
            && ($subject === null || $subject instanceof Contact);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        return match ($attribute) {
            self::CREATE => $this->permission->canCreate($user),
            self::VIEW => $subject instanceof Contact && $this->permission->canView($user, $subject),
            self::EDIT => $subject instanceof Contact && $this->permission->canEdit($user, $subject),
            self::SHARE => $subject instanceof Contact && $this->permission->canShare($user, $subject),
            self::DELETE => $subject instanceof Contact && $this->permission->canDelete($user, $subject),
            default => false,
        };
    }
}
