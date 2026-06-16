<?php

namespace App\Tests\Service;

use App\Entity\Document;
use App\Entity\User;
use App\Service\DocumentMailerService;
use App\Service\DocumentStorageService;
use App\Service\PublicUrlGenerator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\RawMessage;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

final class DocumentMailerServiceTest extends TestCase
{
    #[Test]
    public function itSendsDocumentAsAttachmentWithSenderInformation(): void
    {
        $storageDirectory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'afriocean-fish-document-mailer-test';
        if (!is_dir($storageDirectory)) {
            mkdir($storageDirectory);
        }
        $filePath = $storageDirectory.DIRECTORY_SEPARATOR.'contrat-prive.txt';
        file_put_contents($filePath, 'Document de test');

        $document = (new Document())
            ->setName('Contrat privé')
            ->setFileName('contrat-prive.txt')
            ->setOriginalFileName('contrat.txt')
            ->setMimeType('text/plain')
            ->setFileSize((int) filesize($filePath));
        $actor = (new User())
            ->setEmail('admin@example.com')
            ->setFirstName('Ada')
            ->setLastName('Admin')
            ->setPassword('hash');
        $mailer = new SpyMailer();
        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturn('/documents/1');

        try {
            $service = new DocumentMailerService(
                $mailer,
                new DocumentStorageService(__DIR__, $storageDirectory, 10485760),
                new PublicUrlGenerator($urlGenerator, 'https://gestion.example.com', 'test'),
                new Environment(new FilesystemLoader(dirname(__DIR__, 2).'/templates')),
                'contact@getecoride.com',
                'Afriocean fish Gestion',
            );

            $service->sendDocumentByEmail($document, $actor, 'client@example.com');
        } finally {
            if (is_file($filePath)) {
                unlink($filePath);
            }
            if (is_dir($storageDirectory)) {
                rmdir($storageDirectory);
            }
        }

        $email = $mailer->message;
        self::assertInstanceOf(Email::class, $email);
        self::assertSame('contact@getecoride.com', $email->getFrom()[0]->getAddress());
        self::assertSame('Ada Admin vous envoie un document : Contrat privé', $email->getSubject());
        self::assertSame('client@example.com', $email->getTo()[0]->getAddress());
        self::assertStringContainsString('Ada Admin', (string) $email->getHtmlBody());
        self::assertStringContainsString('admin@example.com', (string) $email->getHtmlBody());
        self::assertStringContainsString('Ada Admin', (string) $email->getTextBody());
        self::assertStringContainsString('admin@example.com', (string) $email->getTextBody());
        self::assertCount(1, $email->getAttachments());
        self::assertSame('contrat.txt', $email->getAttachments()[0]->getFilename());
    }
}

final class SpyMailer implements MailerInterface
{
    public ?RawMessage $message = null;

    public function send(RawMessage $message, ?Envelope $envelope = null): void
    {
        $this->message = $message;
    }
}
