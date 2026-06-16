<?php

namespace App\Security\Voter;

use App\Entity\User;
use App\Service\SecurityAccessService;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

final class ModuleAccessVoter extends Voter
{
    public const ACCESS = 'MODULE_ACCESS';

    public function __construct(private readonly SecurityAccessService $access)
    {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $attribute === self::ACCESS && is_string($subject);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        return $user instanceof User && is_string($subject) && $this->access->canAccessModule($user, $subject);
    }
}
