<?php

declare(strict_types=1);

namespace App\Administration\Command;

use App\Administration\Repository\AuditEntryRepository;
use App\Quiz\Repository\AttemptRepository;
use Psr\Clock\ClockInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Rétention (R4, points ouverts) : journaux d'audit au-delà de 12 mois, tentatives jamais envoyées.
 * À lancer chaque nuit (voir docs/exploitation.md).
 */
#[AsCommand(name: 'callisto:purge', description: 'Supprime les données arrivées au terme de leur durée de conservation.')]
final class PurgeCommand extends Command
{
    public function __construct(
        private readonly AuditEntryRepository $audit,
        private readonly AttemptRepository $attempts,
        private readonly ClockInterface $clock,
        #[Autowire('%callisto.retention.audit_months%')] private readonly int $auditMonths,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $now = $this->clock->now();
        $logs = $this->audit->purgeBefore($now->modify(\sprintf('-%d months', $this->auditMonths)));
        $abandoned = $this->attempts->purgeAbandonedBefore($now->modify('-7 days'));
        $io->success(\sprintf('%d entrée(s) de journal et %d tentative(s) abandonnée(s) supprimées.', $logs, $abandoned));

        return Command::SUCCESS;
    }
}
