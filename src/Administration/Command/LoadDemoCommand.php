<?php

declare(strict_types=1);

namespace App\Administration\Command;

use App\Account\Entity\SchoolClass;
use App\Account\Entity\User;
use App\Account\Entity\UserKind;
use App\Account\Repository\UserRepository;
use App\Progress\Entity\Validation;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Scheb\TwoFactorBundle\Security\TwoFactor\Provider\Totp\TotpAuthenticatorInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Données de démonstration (M10). Refuse de s'exécuter hors des environnements dev et test.
 */
#[AsCommand(name: 'callisto:demo:load', description: 'Charge des comptes et une progression de démonstration (dev uniquement).')]
final class LoadDemoCommand extends Command
{
    /** Secret TOTP de démonstration, public : ne jamais l'utiliser en production. */
    public const DEMO_TOTP_SECRET = 'JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP';
    public const DEMO_PASSWORD = 'callisto-demo-2026';

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserRepository $users,
        private readonly UserPasswordHasherInterface $hasher,
        private readonly ClockInterface $clock,
        private readonly TotpAuthenticatorInterface $totp,
        #[Autowire('%kernel.environment%')] private readonly string $environment,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('code', null, InputOption::VALUE_NONE, 'Affiche seulement le code TOTP courant du compte enseignant de démonstration.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        if (!\in_array($this->environment, ['dev', 'test'], true)) {
            $io->error('Commande réservée aux environnements dev et test.');

            return Command::FAILURE;
        }
        $existing = $this->users->findOneBy(['username' => 'prof']);
        if ($input->getOption('code')) {
            if (null === $existing) {
                $io->error('Charge d\'abord la démonstration.');

                return Command::FAILURE;
            }
            $io->writeln($this->totp->getQRContent($existing));
            $io->success('Code courant : '.\OTPHP\TOTP::createFromSecret(self::DEMO_TOTP_SECRET, $this->clock)->now());

            return Command::SUCCESS;
        }
        if (null !== $existing) {
            $io->warning('La démonstration est déjà chargée (compte « prof »).');

            return Command::SUCCESS;
        }

        $now = $this->clock->now();
        $teacher = new User('prof', 'M. Debeve', UserKind::Teacher, $now);
        $teacher->setPassword($this->hasher->hashPassword($teacher, self::DEMO_PASSWORD), false);
        $teacher->setTotpSecret(self::DEMO_TOTP_SECRET);
        $teacher->setBackupCodes([password_hash('DEMO0-00000', \PASSWORD_DEFAULT)]);
        $this->em->persist($teacher);

        $year = SchoolClass::currentSchoolYear($now);
        $snt = new SchoolClass('2nde3', $year, 'snt', $teacher);
        $nsi = new SchoolClass('1NSI', $year, 'premiere', $teacher);
        $this->em->persist($snt);
        $this->em->persist($nsi);

        $students = [];
        foreach ([['lea.m', 'Léa M.', $snt], ['tom.b', 'Tom B.', $snt], ['ines.k', 'Inès K.', $snt], ['ada.l', 'Ada L.', $nsi], ['alan.t', 'Alan T.', $nsi]] as [$username, $name, $class]) {
            $student = new User($username, $name, UserKind::Student, $now);
            $student->setSchoolClass($class);
            $student->setPassword($this->hasher->hashPassword($student, self::DEMO_PASSWORD), false);
            $this->em->persist($student);
            $students[$username] = $student;
        }
        $this->em->flush();

        $student = static fn (string $who): User => $students[$who] ?? throw new \LogicException($who);
        $grant = function (string $who, string $item, string $daysAgo, ?string $comment = null) use ($student, $teacher, $now): void {
            $this->em->persist(Validation::byTeacher($student($who), $item, $teacher, $now->modify($daysAgo)->setTime(0, 0), $comment, $now->modify($daysAgo)));
        };
        $quiz = function (string $who, string $item, string $daysAgo) use ($student, $now): void {
            $this->em->persist(Validation::fromQuiz($student($who), $item, \Symfony\Component\Uid\Uuid::v7(), $now->modify($daysAgo)));
        };
        $quiz('lea.m', 'snt-int-adresse-ip', '-20 days');
        $quiz('lea.m', 'snt-int-paquets', '-18 days');
        $grant('lea.m', 'snt-int-sf-reseau', '-10 days', 'Très bonne méthode, bien expliquée.');
        $quiz('tom.b', 'snt-int-paquets', '-5 days');
        $quiz('ada.l', 'prem-ent-binaire', '-12 days');
        $quiz('ada.l', 'prem-ent-hexa', '-8 days');
        $grant('ada.l', 'prem-ent-sf-conversion', '-3 days', 'Conversions justes, pense à vérifier par le calcul inverse.');
        $this->em->flush();

        $io->success('Démonstration chargée.');
        $io->table(['Compte', 'Mot de passe', 'Remarque'], [
            ['prof', self::DEMO_PASSWORD, 'Enseignant, /enseignant/connexion, code TOTP : bin/console callisto:demo:load --code'],
            ['lea.m, tom.b, ines.k', self::DEMO_PASSWORD, 'Élèves de SNT (2nde3)'],
            ['ada.l, alan.t', self::DEMO_PASSWORD, 'Élèves de NSI Première (1NSI)'],
        ]);
        $io->text('Secret TOTP de démonstration : '.self::DEMO_TOTP_SECRET.' — code de secours : DEMO0-00000');

        return Command::SUCCESS;
    }
}
