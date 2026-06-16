<?php

namespace App\Command;

use App\Service\InstallationService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:password-manager:install',
    description: 'Initialise les modules et le premier super administrateur.',
)]
final class InstallPasswordManagerCommand extends Command
{
    public function __construct(private readonly InstallationService $installationService)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, 'Adresse e-mail du super administrateur')
            ->addArgument('password', InputArgument::OPTIONAL, 'Mot de passe initial ; omettez-le pour une saisie masquée');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $password = (string) ($input->getArgument('password') ?? '');
        if ($password === '') {
            if (!$input->isInteractive()) {
                $io->error('Le mot de passe est requis en mode non interactif.');

                return Command::INVALID;
            }

            $password = (string) $io->askHidden('Mot de passe initial (12 caractères minimum)');
        }

        try {
            $user = $this->installationService->install(
                (string) $input->getArgument('email'),
                $password,
            );
        } catch (\Throwable $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }

        $io->success(sprintf('Installation terminée. Compte créé : %s', $user->getEmail()));

        return Command::SUCCESS;
    }
}
