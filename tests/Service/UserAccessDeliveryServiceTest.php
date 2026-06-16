<?php

namespace App\Tests\Service;

use App\Entity\PasswordEntry;
use App\Entity\PasswordRevealLink;
use App\Entity\User;
use App\Repository\AppModuleRepository;
use App\Repository\PasswordRevealLinkRepository;
use App\Repository\PasswordShareRepository;
use App\Repository\UserModuleAccessRepository;
use App\Service\PasswordCipher;
use App\Service\PublicUrlGenerator;
use App\Service\SecurityAccessService;
use App\Service\UserAccessDeliveryService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\RawMessage;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

final class UserAccessDeliveryServiceTest extends TestCase
{
    public function testSendPasswordAccessesResetsTheUserPasswordAndEmailsLoginCredentials(): void
    {
        $cipher = $this->cipher();
        $router = $this->createStub(UrlGeneratorInterface::class);
        $router->method('generate')->willReturn('/connexion');
        $mailer = new UserAccessSpyMailer();
        $generatedPassword = null;

        $recipient = (new User())
            ->setEmail('user@example.com')
            ->setFirstName('Jean')
            ->setLastName('Client')
            ->setPassword('old-hash');
        $actor = (new User())
            ->setEmail('admin@example.com')
            ->setFirstName('Ada')
            ->setLastName('Admin')
            ->setRoles(['ROLE_SUPER_ADMIN'])
            ->setPassword('hash');

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())->method('flush');

        $passwordHasher = $this->createMock(UserPasswordHasherInterface::class);
        $passwordHasher
            ->expects($this->once())
            ->method('hashPassword')
            ->with(
                $recipient,
                self::callback(static function (string $plainPassword) use (&$generatedPassword): bool {
                    $generatedPassword = $plainPassword;

                    return strlen($plainPassword) >= 12;
                }),
            )
            ->willReturn('new-hash');

        $service = new UserAccessDeliveryService(
            $this->createStub(PasswordRevealLinkRepository::class),
            $entityManager,
            $cipher,
            new SecurityAccessService(
                $this->createStub(PasswordShareRepository::class),
                $this->createStub(AppModuleRepository::class),
                $this->createStub(UserModuleAccessRepository::class),
            ),
            $passwordHasher,
            $mailer,
            new PublicUrlGenerator($router, 'https://gestion.example.com', 'test'),
            new Environment(new FilesystemLoader(dirname(__DIR__, 2).'/templates')),
            'contact@getecoride.com',
            'Afriocean fish Gestion',
        );

        $service->sendPasswordAccesses($recipient, $actor);

        $email = $mailer->message;
        self::assertInstanceOf(Email::class, $email);
        self::assertSame('new-hash', $recipient->getPassword());
        self::assertNotNull($generatedPassword);
        self::assertStringContainsString('Jean', (string) $email->getHtmlBody());
        self::assertStringContainsString('Client', (string) $email->getHtmlBody());
        self::assertStringContainsString('user@example.com', (string) $email->getHtmlBody());
        self::assertStringContainsString('Mot de passe', (string) $email->getHtmlBody());
        self::assertStringContainsString($generatedPassword, (string) $email->getHtmlBody());
        self::assertStringNotContainsString('CRM', (string) $email->getHtmlBody());
        self::assertStringNotContainsString('Afficher le mot de passe', (string) $email->getHtmlBody());
        self::assertStringNotContainsString('acces-secret', (string) $email->getTextBody());
    }

    public function testConsumePasswordDestroysValidLink(): void
    {
        $cipher = $this->cipher();
        $entry = (new PasswordEntry())
            ->setName('Accès')
            ->setLogin('login@example.com')
            ->setEncryptedPassword($cipher->encrypt('Secret123!'))
            ->setIsValidated(true)
            ->setIsActive(true);
        $link = (new PasswordRevealLink())
            ->setPasswordEntry($entry)
            ->setRecipient((new User())->setEmail('user@example.com')->setPassword('hash'))
            ->setTokenHash(hash('sha256', 'token-test'))
            ->setExpiresAt(new \DateTimeImmutable('+1 day'));

        $repository = $this->createMock(PasswordRevealLinkRepository::class);
        $repository->expects($this->once())->method('findOneByRawToken')->willReturn($link);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())->method('remove')->with($link);
        $entityManager->expects($this->once())->method('flush');

        self::assertSame('Secret123!', $this->service($repository, $entityManager, $cipher)->consumePassword('token-test'));
    }

    public function testConsumeExpiredLinkDeletesWithoutReveal(): void
    {
        $cipher = $this->cipher();
        $entry = (new PasswordEntry())
            ->setName('Accès')
            ->setLogin('login@example.com')
            ->setEncryptedPassword($cipher->encrypt('Secret123!'))
            ->setIsValidated(true)
            ->setIsActive(true);
        $link = (new PasswordRevealLink())
            ->setPasswordEntry($entry)
            ->setRecipient((new User())->setEmail('user@example.com')->setPassword('hash'))
            ->setTokenHash(hash('sha256', 'expired-token'))
            ->setExpiresAt(new \DateTimeImmutable('-1 minute'));

        $repository = $this->createMock(PasswordRevealLinkRepository::class);
        $repository->expects($this->once())->method('findOneByRawToken')->willReturn($link);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())->method('remove')->with($link);
        $entityManager->expects($this->once())->method('flush');

        self::assertNull($this->service($repository, $entityManager, $cipher)->consumePassword('expired-token'));
    }

    private function service(
        PasswordRevealLinkRepository $revealLinkRepository,
        EntityManagerInterface $entityManager,
        PasswordCipher $cipher,
    ): UserAccessDeliveryService {
        return new UserAccessDeliveryService(
            $revealLinkRepository,
            $entityManager,
            $cipher,
            new SecurityAccessService(
                $this->createStub(PasswordShareRepository::class),
                $this->createStub(AppModuleRepository::class),
                $this->createStub(UserModuleAccessRepository::class),
            ),
            $this->createStub(UserPasswordHasherInterface::class),
            $this->createStub(MailerInterface::class),
            new PublicUrlGenerator(
                $this->createStub(UrlGeneratorInterface::class),
                'https://gestion.example.com',
                'test',
            ),
            $this->createStub(Environment::class),
            'contact@getecoride.com',
            'Afriocean fish Gestion',
        );
    }

    private function cipher(): PasswordCipher
    {
        return new PasswordCipher(base64_encode(str_repeat('a', SODIUM_CRYPTO_SECRETBOX_KEYBYTES)));
    }
}

final class UserAccessSpyMailer implements MailerInterface
{
    public ?RawMessage $message = null;

    public function send(RawMessage $message, ?\Symfony\Component\Mailer\Envelope $envelope = null): void
    {
        $this->message = $message;
    }
}
