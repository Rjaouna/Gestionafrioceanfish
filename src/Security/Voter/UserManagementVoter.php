<?php

namespace App\Security\Voter;

use App\Entity\User;
use App\Service\SecurityAccessService;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

final class UserManagementVoter extends Voter
{
    public const MANAGE = 'USER_MANAGE';
    public const DELETE = 'USER_DELETE';

    public function __construct(private readonly SecurityAccessService $access)
    {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::MANAGE, self::DELETE], true)
            && ($subject === null || $subject instanceof User);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $actor = $token->getUser();
        if (!$actor instanceof User) {
            return false;
        }

        return match ($attribute) {
            self::MANAGE => $this->access->canManageUsers($actor),
            self::DELETE => $this->access->isSuperAdmin($actor) && $subject !== $actor,
            default => false,
        };
    }
}
