<?php

namespace App\Service;

use App\Entity\Document;
use App\Entity\User;
use App\Repository\DocumentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final readonly class DocumentService
{
    public function __construct(
        private DocumentRepository $documentRepository,
        private EntityManagerInterface $entityManager,
        private DocumentStorageService $storage,
        private DocumentPermissionService $permission,
        private SecurityAccessService $access,
    ) {
    }

    /** @return array{items: list<Document>, total: int, page: int, pages: int, perPage: int} */
    public function search(User $actor, string $query = '', int $page = 1, int $perPage = 12): array
    {
        if (!$this->permission->canCreate($actor)) {
            throw new AccessDeniedException();
        }

        $page = max(1, $page);
        $perPage = max(1, min(48, $perPage));
        $admin = $this->access->isAdmin($actor);
        $total = $this->documentRepository->countAccessible($actor, $admin, $query);
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min($page, $pages);

        return [
            'items' => $this->documentRepository->searchAccessible($actor, $admin, $query, $page, $perPage),
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
            'perPage' => $perPage,
        ];
    }

    /** @return array{total: int, active: int, archived: int, shared: int} */
    public function dashboardStats(User $actor): array
    {
        if (!$this->permission->canCreate($actor)) {
            throw new AccessDeniedException();
        }

        $admin = $this->access->isAdmin($actor);

        return [
            'total' => $this->documentRepository->countAccessible($actor, $admin),
            'active' => $this->documentRepository->countAccessibleByState($actor, $admin, true),
            'archived' => $this->documentRepository->countAccessibleByState($actor, $admin, false),
            'shared' => $this->documentRepository->countAccessibleShared($actor, $admin),
        ];
    }

    public function create(Document $document, UploadedFile $file, User $actor): Document
    {
        if (!$this->permission->canCreate($actor)) {
            throw new AccessDeniedException();
        }

        $metadata = $this->storage->store($file);
        $document
            ->setFileName($metadata['fileName'])
            ->setOriginalFileName($metadata['originalFileName'])
            ->setMimeType($metadata['mimeType'])
            ->setFileSize($metadata['fileSize'])
            ->setIsActive(true)
            ->setCreatedBy($actor);

        $this->entityManager->persist($document);
        $this->entityManager->flush();

        return $document;
    }

    public function update(Document $document, ?UploadedFile $file, User $actor): Document
    {
        if (!$this->permission->canEdit($actor, $document)) {
            throw new AccessDeniedException();
        }

        if ($file instanceof UploadedFile) {
            $this->storage->replace($document, $file);
        }

        $this->entityManager->flush();

        return $document;
    }

    public function toggleArchive(Document $document, User $actor): bool
    {
        if (!$this->permission->canArchive($actor, $document)) {
            throw new AccessDeniedException();
        }

        $document->setIsActive(!$document->isActive());
        $this->entityManager->flush();

        return $document->isActive();
    }

    public function delete(Document $document, User $actor): void
    {
        if (!$this->permission->canDelete($actor, $document)) {
            throw new AccessDeniedException();
        }

        $this->storage->delete($document);
        $this->entityManager->remove($document);
        $this->entityManager->flush();
    }
}
