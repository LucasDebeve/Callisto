<?php

declare(strict_types=1);

namespace App\Account\Command;

use App\Account\Entity\UserKind;
use App\Account\Repository\UserRepository;
use App\Account\Service\TeacherCredentials;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'callisto:teacher:reset-2fa', description: 'Génère un nouveau secret TOTP et de nouveaux codes de secours (téléphone perdu, rotation).')]
final class ResetTeacherTwoFactorCommand extends Command
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly EntityManagerInterface $em,
        private readonly TeacherCredentials $credentials,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('identifiant', InputArgument::REQUIRED);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $teacher = $this->users->findOneByUsernameAndKind(mb_strtolower((string) $input->getArgument('identifiant')), UserKind::Teacher);
        if (null === $teacher) {
            $io->error('Compte enseignant introuvable.');

            return Command::FAILURE;
        }
        [$uri, $codes] = $this->credentials->enroll($teacher);
        $this->em->flush();
        $this->credentials->printEnrollment($io, $uri, $codes);

        return Command::SUCCESS;
    }
}
