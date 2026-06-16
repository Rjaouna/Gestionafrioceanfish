<?php

namespace App\Service;

use App\Entity\Document;
use App\Entity\DocumentShare;
use App\Entity\User;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Twig\Environment;

final readonly class DocumentMailerService
{
    public function __construct(
        private MailerInterface $mailer,
        private DocumentStorageService $storage,
        private PublicUrlGenerator $publicUrlGenerator,
        private Environment $twig,
        #[Autowire('%env(MAILER_FROM_ADDRESS)%')]
        private string $fromAddress,
        #[Autowire('%env(MAILER_FROM_NAME)%')]
        private string $fromName,
    ) {
    }

    public function sendShareNotification(DocumentShare $share, User $actor): void
    {
        $document = $share->getDocument();
        $recipient = $share->getUser();
        if (!$document || !$recipient || !$recipient->getEmail()) {
            throw new \DomainException('Le partage ne contient pas de destinataire valide.');
        }

        $this->publicUrlGenerator->assertPubliclyReachable();

        $context = [
            'share' => $share,
            'document' => $document,
            'recipient' => $recipient,
            'actor' => $actor,
            'document_url' => $this->publicUrlGenerator->generate('app_document_view', ['id' => $document->getId()]),
        ];

        $this->mailer->send(
            (new Email())
                ->from(new Address($this->fromAddress, $this->fromName))
                ->to(new Address((string) $recipient->getEmail(), $recipient->getDisplayName()))
                ->subject(sprintf('Document partagé : %s', $document->getName()))
                ->html($this->twig->render('email/document_shared.html.twig', $context))
                ->text($this->twig->render('email/document_shared.txt.twig', $context)),
        );
    }

    public function sendDocumentByEmail(Document $document, User $actor, string $recipientEmail): void
    {
        $this->publicUrlGenerator->assertPubliclyReachable();

        $file = $this->storage->file($document);
        $context = [
            'document' => $document,
            'actor' => $actor,
            'document_url' => $this->publicUrlGenerator->generate('app_document_view', ['id' => $document->getId()]),
        ];

        $email = (new Email())
            ->from(new Address($this->fromAddress, $this->fromName))
            ->to(new Address($recipientEmail))
            ->subject(sprintf('%s vous envoie un document : %s', $actor->getDisplayName(), $document->getName()))
            ->html($this->twig->render('email/document_sent.html.twig', $context))
            ->text($this->twig->render('email/document_sent.txt.twig', $context))
            ->attachFromPath(
                $file->getPathname(),
                (string) $document->getOriginalFileName(),
                $document->getMimeType() ?: 'application/octet-stream',
            );

        if ($actor->getEmail() && filter_var($actor->getEmail(), FILTER_VALIDATE_EMAIL)) {
            $email->replyTo(new Address((string) $actor->getEmail(), $actor->getDisplayName()));
        }

        $this->mailer->send($email);
    }
}
