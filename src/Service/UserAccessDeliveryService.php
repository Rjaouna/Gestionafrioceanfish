<?php

namespace App\Service;

use App\Entity\PasswordEntry;
use App\Entity\PasswordRevealLink;
use App\Entity\User;
use App\Repository\PasswordRevealLinkRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Twig\Environment;

final readonly class UserAccessDeliveryService
{
    public function __construct(
        private PasswordRevealLinkRepository $revealLinkRepository,
        private EntityManagerInterface $entityManager,
        private PasswordCipher $cipher,
        private SecurityAccessService $access,
        private UserPasswordHasherInterface $passwordHasher,
        private MailerInterface $mailer,
        private PublicUrlGenerator $publicUrlGenerator,
        private Environment $twig,
        #[Autowire('%env(MAILER_FROM_ADDRESS)%')]
        private string $fromAddress,
        #[Autowire('%env(MAILER_FROM_NAME)%')]
        private string $fromName,
    ) {
    }

    public function canSendTo(User $recipient): bool
    {
        $email = $recipient->getEmail();

        return is_string($email) && filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    public function sendPasswordAccesses(User $recipient, User $actor): void
    {
        if (!$this->access->canManageUsers($actor)) {
            throw new \DomainException('Vous n’avez pas le droit d’envoyer les accès.');
        }

        if (!$this->canSendTo($recipient)) {
            throw new \DomainException('Cet utilisateur n’a pas une adresse e-mail valide.');
        }

        $plainPassword = $this->generateTemporaryPassword();
        $recipient->setPassword($this->passwordHasher->hashPassword($recipient, $plainPassword));
        $this->entityManager->flush();

        $this->mailer->send($this->buildEmail($recipient, $actor, $plainPassword));
    }

    public function consumePassword(string $token): ?string
    {
        $revealLink = $this->revealLinkRepository->findOneByRawToken($token);
        if (!$revealLink instanceof PasswordRevealLink) {
            return null;
        }

        $entry = $revealLink->getPasswordEntry();
        if (
            $revealLink->isExpired()
            || !$entry instanceof PasswordEntry
            || !$entry->isActive()
            || !$entry->isValidated()
        ) {
            $this->entityManager->remove($revealLink);
            $this->entityManager->flush();

            return null;
        }

        $password = $this->cipher->decrypt((string) $entry->getEncryptedPassword());
        $this->entityManager->remove($revealLink);
        $this->entityManager->flush();

        return $password;
    }

    private function buildEmail(User $recipient, User $actor, string $plainPassword): Email
    {
        $context = [
            'recipient' => $recipient,
            'actor' => $actor,
            'plain_password' => $plainPassword,
            'application_url' => $this->publicUrlGenerator->generate('app_login'),
            'role_label' => $this->roleLabel($recipient),
            'module_names' => $this->moduleNames($recipient),
        ];

        return (new Email())
            ->from(new Address($this->fromAddress, $this->fromName))
            ->to(new Address((string) $recipient->getEmail(), $recipient->getDisplayName()))
            ->subject('Vos informations de connexion Afriocean fish Gestion')
            ->html($this->twig->render('emails/password_accesses.html.twig', $context))
            ->text($this->twig->render('emails/password_accesses.txt.twig', $context));
    }

    private function generateTemporaryPassword(): string
    {
        $groups = [
            'ABCDEFGHJKLMNPQRSTUVWXYZ',
            'abcdefghijkmnopqrstuvwxyz',
            '23456789',
            '!@#$%?',
        ];
        $alphabet = implode('', $groups);
        $characters = [];

        foreach ($groups as $group) {
            $characters[] = $group[random_int(0, strlen($group) - 1)];
        }

        while (count($characters) < 14) {
            $characters[] = $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        for ($index = count($characters) - 1; $index > 0; --$index) {
            $swapIndex = random_int(0, $index);
            [$characters[$index], $characters[$swapIndex]] = [$characters[$swapIndex], $characters[$index]];
        }

        return implode('', $characters);
    }

    private function roleLabel(User $user): string
    {
        if (in_array('ROLE_SUPER_ADMIN', $user->getRoles(), true)) {
            return 'Super administrateur';
        }

        if (in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            return 'Administrateur';
        }

        return 'Utilisateur';
    }

    /** @return list<string> */
    private function moduleNames(User $user): array
    {
        $names = [];
        foreach ($user->getModuleAccesses() as $moduleAccess) {
            $name = $moduleAccess->getModule()?->getName();
            if (is_string($name) && $name !== '') {
                $names[] = $name;
            }
        }

        return array_values(array_unique($names));
    }
}
