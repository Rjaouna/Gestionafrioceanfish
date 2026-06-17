<?php

namespace App\Security\Voter;

use App\Entity\User;
use App\Service\SecurityAccessService;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

final class TrashVoter extends Voter
{
    public const VIEW = 'TRASH_VIEW';
    public const RESTORE = 'TRASH_RESTORE';
    public const DELETE_PERMANENTLY = 'TRASH_DELETE_PERMANENTLY';

    public function __construct(private readonly SecurityAccessService $access)
    {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::VIEW, self::RESTORE, self::DELETE_PERMANENTLY], true);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        return $user instanceof User && $this->access->isSuperAdmin($user);
    }
}
