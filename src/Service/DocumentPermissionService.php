<?php

namespace App\Service;

use App\Entity\Document;
use App\Entity\DocumentShare;
use App\Entity\User;
use App\Repository\DocumentShareRepository;

final readonly class DocumentPermissionService
{
    public function __construct(
        private SecurityAccessService $access,
        private DocumentShareRepository $shareRepository,
    ) {
    }

    public function canCreate(User $user): bool
    {
        return $user->isActive() && $this->access->canAccessModule($user, 'documents');
    }

    public function canView(User $user, Document $document): bool
    {
        if (!$user->isActive() || !$document->isActive() || $document->isDeleted()) {
            return false;
        }

        if ($this->access->isAdmin($user)) {
            return true;
        }

        return $this->shareAllows($document, $user, false);
    }

    public function canDownload(User $user, Document $document): bool
    {
        if (!$user->isActive() || !$document->isActive() || $document->isDeleted()) {
            return false;
        }

        if ($this->access->isAdmin($user)) {
            return true;
        }

        return $this->shareAllows($document, $user, true);
    }

    public function canEmail(User $user, Document $document): bool
    {
        return $this->canDownload($user, $document);
    }

    public function canEdit(User $user, Document $document): bool
    {
        return $this->canCreate($user)
            && $document->isActive()
            && !$document->isDeleted()
            && ($this->access->isAdmin($user) || ($this->isCreator($user, $document) && $this->shareAllows($document, $user, false)));
    }

    public function canShare(User $user, Document $document): bool
    {
        return $this->canCreate($user)
            && $document->isActive()
            && !$document->isDeleted()
            && $this->access->isAdmin($user);
    }

    public function canArchive(User $user, Document $document): bool
    {
        return $this->canCreate($user)
            && !$document->isDeleted()
            && ($this->access->isAdmin($user) || ($this->isCreator($user, $document) && $this->shareAllows($document, $user, false)));
    }

    public function canDelete(User $user, Document $document): bool
    {
        return $this->canCreate($user) && !$document->isDeleted() && $this->access->isAdmin($user);
    }

    public function canManageShare(User $user, DocumentShare $share): bool
    {
        $document = $share->getDocument();

        return $document instanceof Document && $this->canShare($user, $document);
    }

    private function shareAllows(Document $document, User $user, bool $download): bool
    {
        $share = $this->shareRepository->findFor($document, $user);
        if (!$share instanceof DocumentShare || !$share->isActive() || $share->isExpired()) {
            return false;
        }

        return $download ? $share->canDownload() : $share->canView();
    }

    private function isCreator(User $user, Document $document): bool
    {
        return $user->getId() !== null
            && $document->getCreatedBy()?->getId() === $user->getId();
    }
}
