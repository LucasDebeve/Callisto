<?php

declare(strict_types=1);

namespace App\Content\Command;

use App\Content\Import\ContentImporter;
use App\Content\Import\ImportReport;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'callisto:content:import', description: 'Valide le dépôt de contenus, affiche le rapport puis publie (EC2).')]
final class ImportContentCommand extends Command
{
    public function __construct(private readonly ContentImporter $importer)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('dossier', InputArgument::OPTIONAL, 'Dossier du dépôt de contenus (par défaut CALLISTO_CONTENT_DIR).')
            ->addOption('check', null, InputOption::VALUE_NONE, 'Valider seulement, sans base de données ni publication (CI du dépôt de contenus).')
            ->addOption('yes', 'y', InputOption::VALUE_NONE, 'Publier sans confirmation si le rapport ne contient aucune erreur.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $directory = $input->getArgument('dossier');
        $directory = \is_string($directory) ? realpath($directory) ?: $directory : null;
        $check = (bool) $input->getOption('check');

        $io->title('Import des contenus');
        $io->text('Dossier : '.($directory ?? $this->importer->defaultDirectory()));
        [$report, $content] = $this->importer->analyze($directory, !$check);
        $this->printReport($io, $report);

        if (!$report->isValid()) {
            $io->error(\sprintf('%d erreur(s) : rien n\'a été publié.', \count($report->getErrors())));

            return Command::FAILURE;
        }
        if ($check) {
            $io->success('Contenu valide.');

            return Command::SUCCESS;
        }
        if (!$input->getOption('yes') && !$io->confirm('Publier ce contenu ?', false)) {
            $io->warning('Publication annulée.');

            return Command::SUCCESS;
        }

        $import = $this->importer->publish($content, $report, 'console');
        $io->success(\sprintf('Contenu publié (import n° %d%s).', $import->getId(), null !== $content->revision ? ', révision '.$content->revision : ''));

        return Command::SUCCESS;
    }

    private function printReport(SymfonyStyle $io, ImportReport $report): void
    {
        $io->section('Rapport');
        $io->table(['Type', 'Nombre'], array_map(static fn ($k, $v) => [$k, $v], array_keys($report->getStats()), $report->getStats()));
        if ([] !== $report->getArchived()) {
            $io->section('Sera archivé (validations conservées)');
            $io->listing($report->getArchived());
        }
        if ([] !== $report->getWarnings()) {
            $io->section('Avertissements');
            $io->listing($report->getWarnings());
        }
        if ([] !== $report->getErrors()) {
            $io->section('Erreurs');
            $io->listing($report->getErrors());
        }
    }
}
