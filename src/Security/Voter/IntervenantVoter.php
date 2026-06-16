<?php

namespace App\Security\Voter;

use App\Entity\Intervenant;
use App\Entity\User;
use App\Service\Maintenance\MaintenanceAccessService;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

final class IntervenantVoter extends Voter
{
    public const VIEW = 'INTERVENANT_VIEW';
    public const CREATE = 'INTERVENANT_CREATE';
    public const EDIT = 'INTERVENANT_EDIT';
    public const ARCHIVE = 'INTERVENANT_ARCHIVE';
    public const DELETE = 'INTERVENANT_DELETE';

    public function __construct(private readonly MaintenanceAccessService $access)
    {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::VIEW, self::CREATE, self::EDIT, self::ARCHIVE, self::DELETE], true)
            && ($subject === null || $subject instanceof Intervenant);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        return match ($attribute) {
            self::VIEW => $subject instanceof Intervenant && $this->access->canAccess($user),
            self::CREATE => $this->access->canCreate($user),
            self::EDIT => $subject instanceof Intervenant && $this->access->canEdit($user),
            self::ARCHIVE => $subject instanceof Intervenant && $this->access->canArchive($user),
            self::DELETE => $subject instanceof Intervenant && $this->access->canDelete($user),
            default => false,
        };
    }
}
