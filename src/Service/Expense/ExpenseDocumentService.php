<?php

namespace App\Service\Expense;

use App\Entity\Expense;
use App\Entity\ExpenseDocument;
use App\Entity\User;
use App\Repository\ExpenseDocumentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final readonly class ExpenseDocumentService
{
    /** @var list<string> */
    private const ALLOWED_EXTENSIONS = ['pdf', 'png', 'jpg', 'jpeg', 'webp', 'doc', 'docx', 'xls', 'xlsx'];

    /** @var list<string> */
    private const ALLOWED_MIME_TYPES = [
        'application/pdf',
        'image/png',
        'image/jpeg',
        'image/webp',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    ];

    public function __construct(
        private ExpenseDocumentRepository $repository,
        private EntityManagerInterface $entityManager,
        private ExpenseAccessService $access,
        #[Autowire('%kernel.project_dir%')]
        private string $projectDir,
        #[Autowire('%env(EXPENSE_STORAGE_DIRECTORY)%')]
        private string $storageDirectory,
        #[Autowire('%env(int:EXPENSE_MAX_FILE_SIZE)%')]
        private int $maxFileSize,
    ) {
    }

    /** @return list<string> */
    public function allowedMimeTypes(): array
    {
        return self::ALLOWED_MIME_TYPES;
    }

    public function maxFileSize(): int
    {
        return $this->maxFileSize;
    }

    public function replacePrimary(Expense $expense, UploadedFile $file, string $documentType, User $actor): ExpenseDocument
    {
        if (!$this->access->canEdit($actor, $expense)) {
            throw new AccessDeniedException();
        }

        $previous = $this->repository->findPrimaryForExpense($expense);
        if ($previous instanceof ExpenseDocument) {
            $this->deletePhysical($previous);
            $previous->setIsActive(false);
        }

        $document = $this->store($expense, $file, $documentType, $actor);
        $this->entityManager->flush();

        return $document;
    }

    public function file(ExpenseDocument $document, User $actor): File
    {
        if (!$this->access->canDownloadDocument($actor, $document)) {
            throw new AccessDeniedException();
        }

        $fileName = $document->getFileName();
        if (!$fileName) {
            throw new \RuntimeException('Le justificatif est introuvable.');
        }

        $path = $this->path($fileName);
        if (!is_file($path)) {
            throw new \RuntimeException('Le justificatif est introuvable.');
        }

        return new File($path);
    }

    public function downloadFileName(ExpenseDocument $document): string
    {
        $expense = $document->getExpense();
        $extension = mb_strtolower((string) pathinfo((string) $document->getOriginalFileName(), PATHINFO_EXTENSION));
        $base = preg_replace('/[<>:"\/\\\\|?*\x00-\x1F\x7F]+/u', '-', (string) ($expense?->getReference() ?: 'depense'));
        $base = trim((string) $base, " .-\t\n\r\0\x0B");

        return $extension ? $base.'.'.$extension : $base;
    }

    public function delete(ExpenseDocument $document, User $actor): void
    {
        if (!$this->access->canDeleteDocument($actor, $document)) {
            throw new AccessDeniedException();
        }

        $this->deletePhysical($document);
        $this->entityManager->remove($document);
        $this->entityManager->flush();
    }

    private function store(Expense $expense, UploadedFile $file, string $documentType, User $actor): ExpenseDocument
    {
        $this->validate($file);
        $originalFileName = $file->getClientOriginalName();
        $mimeType = $file->getMimeType() ?: 'application/octet-stream';
        $fileSize = $file->getSize();
        $extension = mb_strtolower((string) pathinfo($originalFileName, PATHINFO_EXTENSION));
        $storedName = sprintf('%s.%s', bin2hex(random_bytes(24)), $extension);
        $file->move($this->directory(), $storedName);
        $storedPath = $this->path($storedName);

        $document = (new ExpenseDocument())
            ->setExpense($expense)
            ->setOriginalFileName($originalFileName)
            ->setFileName($storedName)
            ->setMimeType($mimeType)
            ->setFileSize((int) ($fileSize ?? filesize($storedPath)))
            ->setDocumentType($documentType)
            ->setCreatedBy($actor);

        $expense->addDocument($document);
        $this->entityManager->persist($document);

        return $document;
    }

    private function validate(UploadedFile $file): void
    {
        if (!$file->isValid()) {
            throw new \DomainException('Le fichier envoyé est invalide.');
        }

        if ($file->getSize() !== null && $file->getSize() > $this->maxFileSize) {
            throw new \DomainException('Le fichier dépasse la taille maximale autorisée.');
        }

        $extension = mb_strtolower((string) pathinfo($file->getClientOriginalName(), PATHINFO_EXTENSION));
        if (!in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            throw new \DomainException('Cette extension de fichier n’est pas autorisée.');
        }

        $mimeType = $file->getMimeType();
        if (!$mimeType || !in_array($mimeType, self::ALLOWED_MIME_TYPES, true)) {
            throw new \DomainException('Ce type de fichier n’est pas autorisé.');
        }
    }

    private function deletePhysical(ExpenseDocument $document): void
    {
        $fileName = $document->getFileName();
        if (!$fileName) {
            return;
        }

        $path = $this->path($fileName);
        if (is_file($path)) {
            unlink($path);
        }
    }

    private function path(string $fileName): string
    {
        return $this->directory().DIRECTORY_SEPARATOR.basename($fileName);
    }

    private function directory(): string
    {
        $directory = $this->storageDirectory;
        if (!str_starts_with($directory, DIRECTORY_SEPARATOR) && !preg_match('/^[A-Za-z]:[\\\\\\/]/', $directory)) {
            $directory = $this->projectDir.DIRECTORY_SEPARATOR.$directory;
        }

        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new \RuntimeException('Impossible de créer le dossier privé des dépenses.');
        }

        return rtrim($directory, "\\/");
    }
}
