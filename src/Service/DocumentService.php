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

    /**
     * @param array{category?: string, issuer?: string, language?: string, status?: string} $filters
     *
     * @return array{items: list<Document>, total: int, page: int, pages: int, perPage: int, filters: array{category: string, issuer: string, language: string, status: string}}
     */
    public function search(User $actor, string $query = '', int $page = 1, int $perPage = 12, array $filters = []): array
    {
        if (!$this->permission->canCreate($actor)) {
            throw new AccessDeniedException();
        }

        $page = max(1, $page);
        $perPage = max(1, min(48, $perPage));
        $admin = $this->access->isAdmin($actor);
        $filters = $this->normalizeFilters($filters);
        $total = $this->documentRepository->countAccessible($actor, $admin, $query, $filters);
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min($page, $pages);

        return [
            'items' => $this->documentRepository->searchAccessible($actor, $admin, $query, $page, $perPage, $filters),
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
            'perPage' => $perPage,
            'filters' => $filters,
        ];
    }

    /** @return array{categories: list<string>, issuers: list<string>, languages: list<string>, statuses: array<string, string>} */
    public function filterChoices(User $actor): array
    {
        if (!$this->permission->canCreate($actor)) {
            throw new AccessDeniedException();
        }

        $admin = $this->access->isAdmin($actor);

        return [
            'categories' => $this->documentRepository->distinctAccessibleValues($actor, $admin, 'category'),
            'issuers' => $this->documentRepository->distinctAccessibleValues($actor, $admin, 'issuer'),
            'languages' => $this->documentRepository->distinctAccessibleValues($actor, $admin, 'language'),
            'statuses' => Document::STATUS_LABELS,
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
            ->setCreatedBy($actor);

        $this->prepareForSave($document);

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

        $this->prepareForSave($document);
        $this->entityManager->flush();

        return $document;
    }

    public function toggleArchive(Document $document, User $actor): bool
    {
        if (!$this->permission->canArchive($actor, $document)) {
            throw new AccessDeniedException();
        }

        $document->setIsActive(!$document->isActive());
        $document->setStatus($document->isActive() ? Document::STATUS_ACTIVE : Document::STATUS_ARCHIVED);
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

    private function prepareForSave(Document $document): void
    {
        if ($document->getInternalReference() === null) {
            $document->setInternalReference($this->nextInternalReference());
        }

        $document->setDocumentDate($document->getCreatedAt() ?? new \DateTimeImmutable());
        $document->setIsActive($document->getStatus() !== Document::STATUS_ARCHIVED);
    }

    private function nextInternalReference(): string
    {
        $prefix = 'DOC-'.(new \DateTimeImmutable())->format('Y');
        $number = $this->nextInternalReferenceNumber($prefix);

        do {
            $reference = sprintf('%s-%03d', $prefix, $number);
            ++$number;
        } while ($this->documentRepository->findOneBy(['internalReference' => $reference]) instanceof Document);

        return $reference;
    }

    private function nextInternalReferenceNumber(string $prefix): int
    {
        $highest = 0;
        foreach ($this->documentRepository->findInternalReferencesByPrefix($prefix) as $reference) {
            if (preg_match('/^'.preg_quote($prefix, '/').'-(\d+)$/', $reference, $matches) !== 1) {
                continue;
            }

            $highest = max($highest, (int) $matches[1]);
        }

        return $highest + 1;
    }

    /**
     * @param array{category?: string, issuer?: string, language?: string, status?: string} $filters
     *
     * @return array{category: string, issuer: string, language: string, status: string}
     */
    private function normalizeFilters(array $filters): array
    {
        $normalized = [
            'category' => trim((string) ($filters['category'] ?? '')),
            'issuer' => trim((string) ($filters['issuer'] ?? '')),
            'language' => trim((string) ($filters['language'] ?? '')),
            'status' => trim((string) ($filters['status'] ?? '')),
        ];

        if ($normalized['status'] !== '' && !isset(Document::STATUS_LABELS[$normalized['status']])) {
            $normalized['status'] = '';
        }

        return $normalized;
    }
}
