<?php

namespace App\Service;

use App\Entity\AppModule;
use App\Entity\User;
use App\Repository\AppModuleRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final readonly class InstallationService
{
    public function __construct(
        private AppModuleRepository $moduleRepository,
        private UserRepository $userRepository,
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public function install(string $email, string $plainPassword): User
    {
        if (strlen($plainPassword) < 12) {
            throw new \DomainException('Le mot de passe doit contenir au moins 12 caractères.');
        }

        $definitions = [
            ['Coffre de mots de passe', 'passwords', 'bi-key', 'app_password_index', 'Gestion et partage sécurisé des accès.'],
            ['Gestion des documents', 'documents', 'bi-folder2-open', 'app_document_index', 'Documents privés, partage sécurisé et téléchargement contrôlé.'],
            ['Carnet de contacts', 'contacts', 'bi-person-lines-fill', 'app_contact_index', 'Fournisseurs, clients, dépanneurs et contacts partagés.'],
            ['Maintenance', 'maintenance', 'bi-tools', 'app_maintenance_intervenant_index', 'Intervenants, contrats et interventions de maintenance.'],
            ['Dépenses', 'expenses', 'bi-cash-coin', 'app_expense_index', 'Gestion financière des dépenses, justificatifs et validations.'],
            ['Cout de revient', 'cout-revient', 'bi-calculator', 'app_cout_revient_index', 'Calcul des couts de production, rendements et marges par lot.'],
            ['Composition usine', 'factory', 'bi-building-gear', 'app_factory_unit_index', 'Tunnels, chambres froides, zones de stockage et selections usine.'],
            ['Réceptions', 'receptions', 'bi-clipboard2-check', 'app_fish_reception_index', 'Réception poisson, traitement, emballage, congélation, stockage et expédition.'],
            ['Intérimaires', 'interimaires', 'bi-person-vcard', 'app_interim_worker_index', 'Fiches de poste, suivi RH et documents des intérimaires.'],
            ['Agenda - RDV', 'agenda', 'bi-calendar-check', 'app_appointment_calendar', 'Calendrier professionnel, rendez-vous, participants et rappels.'],
            ['Statistiques', 'statistics', 'bi-graph-up-arrow', 'app_statistics_index', 'Graphiques et indicateurs de pilotage.'],
            ['Gestion des utilisateurs', 'users', 'bi-people', 'app_user_index', 'Comptes, rôles et accès modules.'],
            ['Gestion des modules', 'modules', 'bi-grid', 'app_module_index', 'Catalogue des modules applicatifs.'],
        ];

        foreach ($definitions as [$name, $slug, $icon, $route, $description]) {
            if ($slug === 'interimaires') {
                $name = 'Fiches intérimaires';
                $description = 'Fiches de poste, suivi RH, statuts et documents des intérimaires.';
            }

            $module = $this->moduleRepository->findOneBy(['slug' => $slug]) ?? new AppModule();
            $module
                ->setName($name)
                ->setSlug($slug)
                ->setIcon($icon)
                ->setRouteName($route)
                ->setDescription($description)
                ->setIsActive(true);
            $this->entityManager->persist($module);
        }

        $user = $this->userRepository->findOneBy(['email' => mb_strtolower(trim($email))]) ?? new User();
        $user
            ->setEmail($email)
            ->setRoles(['ROLE_SUPER_ADMIN'])
            ->setIsActive(true);
        $user->setPassword($this->passwordHasher->hashPassword($user, $plainPassword));
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }
}
