<?php

namespace App\Service;

use App\Entity\InterimWorker;
use App\Entity\InterimWorkerDocument;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final readonly class InterimWorkerStorageService
{
    /** @var list<string> */
    private const PHOTO_MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

    /** @var list<string> */
    private const DOCUMENT_MIME_TYPES = ['image/jpeg', 'image/png', 'application/pdf'];

    /** @var list<string> */
    private const PHOTO_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp'];

    /** @var list<string> */
    private const DOCUMENT_EXTENSIONS = ['jpg', 'jpeg', 'png', 'pdf'];

    private const PHOTO_MAX_SIZE = 2097152;
    private const DOCUMENT_MAX_SIZE = 10485760;

    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        private string $projectDir,
    ) {
    }

    /** @return list<string> */
    public function photoMimeTypes(): array
    {
        return self::PHOTO_MIME_TYPES;
    }

    /** @return list<string> */
    public function documentMimeTypes(): array
    {
        return self::DOCUMENT_MIME_TYPES;
    }

    public function photoMaxSize(): int
    {
        return self::PHOTO_MAX_SIZE;
    }

    public function documentMaxSize(): int
    {
        return self::DOCUMENT_MAX_SIZE;
    }

    public function replacePhoto(InterimWorker $worker, UploadedFile $file): void
    {
        $oldFileName = $worker->getPhotoFileName();
        $metadata = $this->store($file, 'photos', self::PHOTO_MIME_TYPES, self::PHOTO_EXTENSIONS, self::PHOTO_MAX_SIZE);

        $worker
            ->setPhotoFileName($metadata['fileName'])
            ->setPhotoOriginalFileName($metadata['originalFileName'])
            ->setPhotoMimeType($metadata['mimeType'])
            ->setPhotoFileSize($metadata['fileSize']);

        if ($oldFileName) {
            $this->deleteByName($oldFileName, 'photos');
        }
    }

    public function createDocument(UploadedFile $file): InterimWorkerDocument
    {
        $metadata = $this->store($file, 'documents', self::DOCUMENT_MIME_TYPES, self::DOCUMENT_EXTENSIONS, self::DOCUMENT_MAX_SIZE);

        return (new InterimWorkerDocument())
            ->setFileName($metadata['fileName'])
            ->setOriginalFileName($metadata['originalFileName'])
            ->setMimeType($metadata['mimeType'])
            ->setFileSize($metadata['fileSize']);
    }

    public function photoFile(InterimWorker $worker): File
    {
        $fileName = $worker->getPhotoFileName();
        if (!$fileName) {
            throw new \RuntimeException('Photo introuvable.');
        }

        return $this->file($fileName, 'photos');
    }

    public function documentFile(InterimWorkerDocument $document): File
    {
        $fileName = $document->getFileName();
        if (!$fileName) {
            throw new \RuntimeException('Document introuvable.');
        }

        return $this->file($fileName, 'documents');
    }

    public function deleteDocument(InterimWorkerDocument $document): void
    {
        $fileName = $document->getFileName();
        if ($fileName) {
            $this->deleteByName($fileName, 'documents');
        }
    }

    public function deleteFilesForWorker(InterimWorker $worker): void
    {
        $photoFileName = $worker->getPhotoFileName();
        if ($photoFileName) {
            $this->deleteByName($photoFileName, 'photos');
        }

        foreach ($worker->getDocuments() as $document) {
            $this->deleteDocument($document);
        }
    }

    /** @return array{fileName: string, originalFileName: string, mimeType: string, fileSize: int} */
    private function store(UploadedFile $file, string $subDirectory, array $mimeTypes, array $extensions, int $maxSize): array
    {
        $this->validate($file, $mimeTypes, $extensions, $maxSize);
        $extension = $this->extension($file);
        $originalFileName = $file->getClientOriginalName();
        $mimeType = $file->getMimeType() ?: 'application/octet-stream';
        $storedName = sprintf('%s.%s', bin2hex(random_bytes(24)), $extension);
        $file->move($this->directory($subDirectory), $storedName);

        return [
            'fileName' => $storedName,
            'originalFileName' => $originalFileName,
            'mimeType' => $mimeType,
            'fileSize' => (int) filesize($this->path($storedName, $subDirectory)),
        ];
    }

    /** @param list<string> $mimeTypes @param list<string> $extensions */
    private function validate(UploadedFile $file, array $mimeTypes, array $extensions, int $maxSize): void
    {
        if (!$file->isValid()) {
            throw new \DomainException('Le fichier envoye est invalide.');
        }

        if ($file->getSize() !== null && $file->getSize() > $maxSize) {
            throw new \DomainException('Le fichier depasse la taille maximale autorisee.');
        }

        if (!in_array($this->extension($file), $extensions, true)) {
            throw new \DomainException('Cette extension de fichier n’est pas autorisee.');
        }

        $mimeType = $file->getMimeType();
        if (!$mimeType || !in_array($mimeType, $mimeTypes, true)) {
            throw new \DomainException('Ce type de fichier n’est pas autorise.');
        }
    }

    private function extension(UploadedFile $file): string
    {
        return mb_strtolower((string) pathinfo($file->getClientOriginalName(), PATHINFO_EXTENSION));
    }

    private function file(string $fileName, string $subDirectory): File
    {
        $path = $this->path($fileName, $subDirectory);
        if (!is_file($path)) {
            throw new \RuntimeException('Fichier introuvable.');
        }

        return new File($path);
    }

    private function deleteByName(string $fileName, string $subDirectory): void
    {
        $path = $this->path($fileName, $subDirectory);
        if (is_file($path)) {
            unlink($path);
        }
    }

    private function path(string $fileName, string $subDirectory): string
    {
        return $this->directory($subDirectory).DIRECTORY_SEPARATOR.basename($fileName);
    }

    private function directory(string $subDirectory): string
    {
        $directory = $this->projectDir.DIRECTORY_SEPARATOR.'var'.DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'interimaires'.DIRECTORY_SEPARATOR.$subDirectory;
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new \RuntimeException('Impossible de créer le dossier privé des intérimaires.');
        }

        return $directory;
    }
}
