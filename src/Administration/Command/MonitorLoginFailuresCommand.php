<?php

declare(strict_types=1);

namespace App\Administration\Command;

use App\Administration\AuditLogger;
use App\Administration\Repository\AuditEntryRepository;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * M13 : alerte si les échecs de connexion explosent. Code de sortie 2 au-delà du seuil,
 * pour une supervision par cron (envoi d'e-mail) ou par un outil externe.
 */
#[AsCommand(name: 'callisto:monitor:login-failures', description: 'Vérifie le nombre d\'échecs de connexion récents.')]
final class MonitorLoginFailuresCommand extends Command
{
    public function __construct(
        private readonly AuditEntryRepository $audit,
        private readonly ClockInterface $clock,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('minutes', null, InputOption::VALUE_REQUIRED, 'Fenêtre observée', '15')
            ->addOption('seuil', null, InputOption::VALUE_REQUIRED, 'Nombre d\'échecs déclenchant l\'alerte', '50');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $minutes = max(1, (int) $input->getOption('minutes'));
        $threshold = max(1, (int) $input->getOption('seuil'));
        $count = $this->audit->countSince(AuditLogger::LOGIN_FAILURE, $this->clock->now()->modify(\sprintf('-%d minutes', $minutes)));
        $output->writeln(\sprintf('%d échec(s) de connexion sur les %d dernières minutes (seuil %d).', $count, $minutes, $threshold));
        if ($count >= $threshold) {
            $this->logger->critical('Pic d\'échecs de connexion', ['echecs' => $count, 'minutes' => $minutes]);

            return 2;
        }

        return Command::SUCCESS;
    }
}
