<?php

namespace App\Service;

use App\Entity\Contact;
use App\Entity\ContactShare;
use App\Entity\User;
use App\Repository\ContactShareRepository;

final readonly class ContactPermissionService
{
    public function __construct(
        private SecurityAccessService $access,
        private ContactShareRepository $shareRepository,
    ) {
    }

    public function canCreate(User $user): bool
    {
        return $user->isActive() && $this->access->canAccessModule($user, 'contacts');
    }

    public function canView(User $user, Contact $contact): bool
    {
        if (!$user->isActive() || !$contact->isActive()) {
            return false;
        }

        if ($this->access->isAdmin($user)) {
            return true;
        }

        return $this->shareAllows($contact, $user);
    }

    public function canEdit(User $user, Contact $contact): bool
    {
        return $this->canCreate($user)
            && $contact->isActive()
            && ($this->access->isAdmin($user) || ($this->isCreator($user, $contact) && $this->shareAllows($contact, $user)));
    }

    public function canShare(User $user, Contact $contact): bool
    {
        return $this->canCreate($user)
            && $contact->isActive()
            && $this->access->isAdmin($user);
    }

    public function canDelete(User $user, Contact $contact): bool
    {
        return $this->canCreate($user)
            && $contact->isActive()
            && $this->access->isAdmin($user);
    }

    private function isCreator(User $user, Contact $contact): bool
    {
        return $user->getId() !== null
            && $contact->getCreatedBy()?->getId() === $user->getId();
    }

    private function shareAllows(Contact $contact, User $user): bool
    {
        $share = $this->shareRepository->findFor($contact, $user);

        return $share instanceof ContactShare && $share->isActive() && $share->canView();
    }
}
