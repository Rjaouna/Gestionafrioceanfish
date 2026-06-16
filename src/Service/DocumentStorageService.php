<?php

namespace App\Service;

use App\Entity\Document;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final readonly class DocumentStorageService
{
    /** @var list<string> */
    private const ALLOWED_EXTENSIONS = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'csv', 'png', 'jpg', 'jpeg', 'webp'];

    /** @var list<string> */
    private const ALLOWED_MIME_TYPES = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'text/plain',
        'text/csv',
        'image/png',
        'image/jpeg',
        'image/webp',
    ];

    /** @var list<string> */
    private const DANGEROUS_EXTENSIONS = ['bat', 'cmd', 'com', 'exe', 'html', 'js', 'jar', 'msi', 'php', 'phtml', 'phar', 'pl', 'ps1', 'py', 'rb', 'scr', 'sh', 'vbs'];

    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        private string $projectDir,
        #[Autowire('%env(DOCUMENT_STORAGE_DIRECTORY)%')]
        private string $storageDirectory,
        #[Autowire('%env(int:DOCUMENT_MAX_FILE_SIZE)%')]
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

    public function downloadFileName(Document $document): string
    {
        $extension = mb_strtolower((string) pathinfo((string) $document->getOriginalFileName(), PATHINFO_EXTENSION));
        $title = preg_replace('/[<>:"\/\\\\|?*\x00-\x1F\x7F]+/u', '-', (string) $document->getName());
        $title = trim((string) $title, " .-\t\n\r\0\x0B");

        if ($title === '') {
            $title = 'document';
        }

        if ($extension === '' || mb_strtolower((string) pathinfo($title, PATHINFO_EXTENSION)) === $extension) {
            return $title;
        }

        return $title.'.'.$extension;
    }

    /** @return array{fileName: string, originalFileName: string, mimeType: string, fileSize: int} */
    public function store(UploadedFile $file): array
    {
        $this->validate($file);
        $extension = $this->extension($file);
        $originalFileName = $file->getClientOriginalName();
        $mimeType = $file->getMimeType() ?: 'application/octet-stream';
        $storedName = sprintf('%s.%s', bin2hex(random_bytes(24)), $extension);
        $file->move($this->directory(), $storedName);

        return [
            'fileName' => $storedName,
            'originalFileName' => $originalFileName,
            'mimeType' => $mimeType,
            'fileSize' => (int) filesize($this->path($storedName)),
        ];
    }

    public function replace(Document $document, UploadedFile $file): void
    {
        $oldFileName = $document->getFileName();
        $metadata = $this->store($file);
        $document
            ->setFileName($metadata['fileName'])
            ->setOriginalFileName($metadata['originalFileName'])
            ->setMimeType($metadata['mimeType'])
            ->setFileSize($metadata['fileSize']);

        if ($oldFileName) {
            $this->deleteByName($oldFileName);
        }
    }

    public function file(Document $document): File
    {
        $fileName = $document->getFileName();
        if (!$fileName) {
            throw new \RuntimeException('Le fichier du document est introuvable.');
        }

        $path = $this->path($fileName);
        if (!is_file($path)) {
            throw new \RuntimeException('Le fichier du document est introuvable.');
        }

        return new File($path);
    }

    public function delete(Document $document): void
    {
        $fileName = $document->getFileName();
        if ($fileName) {
            $this->deleteByName($fileName);
        }
    }

    private function validate(UploadedFile $file): void
    {
        if (!$file->isValid()) {
            throw new \DomainException('Le fichier envoyé est invalide.');
        }

        if ($file->getSize() !== null && $file->getSize() > $this->maxFileSize) {
            throw new \DomainException('Le fichier dépasse la taille maximale autorisée.');
        }

        $extension = $this->extension($file);
        if (in_array($extension, self::DANGEROUS_EXTENSIONS, true) || !in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            throw new \DomainException('Cette extension de fichier n’est pas autorisée.');
        }

        $mimeType = $file->getMimeType();
        if (!$mimeType || !in_array($mimeType, self::ALLOWED_MIME_TYPES, true)) {
            throw new \DomainException('Ce type de fichier n’est pas autorisé.');
        }
    }

    private function extension(UploadedFile $file): string
    {
        return mb_strtolower((string) pathinfo($file->getClientOriginalName(), PATHINFO_EXTENSION));
    }

    private function deleteByName(string $fileName): void
    {
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
            throw new \RuntimeException('Impossible de créer le dossier privé des documents.');
        }

        return rtrim($directory, "\\/");
    }
}
