<?php

declare(strict_types=1);

namespace App\Account\Command;

use App\Account\Entity\User;
use App\Account\Entity\UserKind;
use App\Account\Repository\UserRepository;
use App\Account\Service\TeacherCredentials;
use App\Account\Validator\PasswordRules;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Création du compte enseignant, en ligne de commande uniquement : la double authentification
 * est configurée dès la création (S5), il n'existe aucun parcours web d'enrôlement.
 */
#[AsCommand(name: 'callisto:teacher:create', description: 'Crée un compte enseignant avec double authentification TOTP.')]
final class CreateTeacherCommand extends Command
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $hasher,
        private readonly TeacherCredentials $credentials,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('identifiant', InputArgument::REQUIRED)
            ->addArgument('nom', InputArgument::OPTIONAL, 'Nom affiché', 'Enseignant');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $username = mb_strtolower((string) $input->getArgument('identifiant'));
        if (null !== $this->users->findOneBy(['username' => $username])) {
            $io->error('Cet identifiant existe déjà.');

            return Command::FAILURE;
        }

        $password = (string) $io->askHidden('Mot de passe (12 caractères au moins)', static function (?string $value) use ($username): string {
            $value = (string) $value;
            if (mb_strlen($value) < 12 || [] !== PasswordRules::violations($value, $username)) {
                throw new \RuntimeException('Mot de passe refusé : 12 caractères au moins, pas courant, sans l\'identifiant, deux sortes de caractères.');
            }

            return $value;
        });

        $teacher = new User($username, (string) $input->getArgument('nom'), UserKind::Teacher);
        $teacher->setPassword($this->hasher->hashPassword($teacher, $password), false);
        [$uri, $codes] = $this->credentials->enroll($teacher);
        $this->em->persist($teacher);
        $this->em->flush();

        $this->credentials->printEnrollment($io, $uri, $codes);

        return Command::SUCCESS;
    }
}
