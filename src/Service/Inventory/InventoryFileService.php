<?php

namespace App\Service\Inventory;

use App\Entity\InventoryAttachment;
use App\Entity\InventoryItem;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final readonly class InventoryFileService
{
    /** @var list<string> */
    private const ALLOWED_EXTENSIONS = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt', 'csv', 'png', 'jpg', 'jpeg', 'webp'];

    /** @var list<string> */
    private const ALLOWED_MIME_TYPES = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'text/plain',
        'text/csv',
        'image/png',
        'image/jpeg',
        'image/webp',
    ];

    private const MAX_FILE_SIZE = 10485760;

    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        private string $projectDir,
    ) {
    }

    /** @return list<string> */
    public function allowedMimeTypes(): array
    {
        return self::ALLOWED_MIME_TYPES;
    }

    public function maxFileSize(): int
    {
        return self::MAX_FILE_SIZE;
    }

    public function store(InventoryItem $item, UploadedFile $file, string $type = 'document'): InventoryAttachment
    {
        $this->validate($file);
        $extension = mb_strtolower((string) pathinfo($file->getClientOriginalName(), PATHINFO_EXTENSION));
        $fileName = sprintf('%s.%s', bin2hex(random_bytes(24)), $extension);
        $file->move($this->directory(), $fileName);

        $attachment = (new InventoryAttachment())
            ->setItem($item)
            ->setAttachmentType($type)
            ->setOriginalFileName($file->getClientOriginalName())
            ->setFileName($fileName)
            ->setMimeType($file->getMimeType() ?: 'application/octet-stream')
            ->setFileSize((int) filesize($this->path($fileName)));

        $item->addAttachment($attachment);

        return $attachment;
    }

    public function file(InventoryAttachment $attachment): File
    {
        $fileName = $attachment->getFileName();
        if (!$fileName) {
            throw new \RuntimeException('Le fichier est introuvable.');
        }

        $path = $this->path($fileName);
        if (!is_file($path)) {
            throw new \RuntimeException('Le fichier est introuvable.');
        }

        return new File($path);
    }

    public function delete(InventoryAttachment $attachment): void
    {
        $fileName = $attachment->getFileName();
        if ($fileName) {
            $this->deleteByName($fileName);
        }
    }

    public function deleteFilesForItem(InventoryItem $item): void
    {
        foreach ($item->getAttachments() as $attachment) {
            $this->delete($attachment);
        }
    }

    private function validate(UploadedFile $file): void
    {
        if (!$file->isValid()) {
            throw new \DomainException('Le fichier envoyé est invalide.');
        }

        if ($file->getSize() !== null && $file->getSize() > self::MAX_FILE_SIZE) {
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
        $directory = $this->projectDir.DIRECTORY_SEPARATOR.'var'.DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'inventory';
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new \RuntimeException('Impossible de créer le dossier privé inventaire.');
        }

        return $directory;
    }
}
