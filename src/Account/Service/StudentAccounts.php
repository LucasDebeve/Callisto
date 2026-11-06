<?php

declare(strict_types=1);

namespace App\Account\Service;

use App\Account\Entity\SchoolClass;
use App\Account\Entity\User;
use App\Account\Entity\UserKind;
use App\Account\Repository\SchoolClassRepository;
use App\Account\Repository\UserRepository;
use App\Administration\AuditLogger;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Gestion des comptes élèves par l'enseignant : import de classe, réinitialisation, archivage (EP1, EP2).
 * Les mots de passe provisoires ne sont jamais stockés ni journalisés en clair (S1) : ils sont renvoyés
 * une seule fois pour la liste imprimable.
 */
final readonly class StudentAccounts
{
    public function __construct(
        private EntityManagerInterface $em,
        private UserRepository $users,
        private SchoolClassRepository $classes,
        private UserPasswordHasherInterface $hasher,
        private ProvisionalPasswordGenerator $generator,
        private ClockInterface $clock,
        private AuditLogger $audit,
    ) {
    }

    /**
     * @param list<array{username: string, displayName: string, className: string, line: int}> $rows
     *
     * @return array{created: list<array{user: User, password: string}>, errors: list<string>}
     */
    public function import(User $teacher, array $rows, string $levelCode, string $schoolYear): array
    {
        $errors = [];
        $taken = array_flip($this->users->existingUsernames(array_column($rows, 'username')));
        foreach ($rows as $row) {
            if (isset($taken[$row['username']])) {
                $errors[] = \sprintf('Ligne %d : l\'identifiant « %s » existe déjà.', $row['line'], $row['username']);
            }
        }
        if ([] !== $errors) {
            return ['created' => [], 'errors' => $errors];
        }

        $created = [];
        $classCache = [];
        foreach ($rows as $row) {
            $class = $classCache[$row['className']] ??= $this->findOrCreateClass($teacher, $row['className'], $levelCode, $schoolYear);
            $user = new User($row['username'], $row['displayName'], UserKind::Student, $this->clock->now());
            $user->setSchoolClass($class);
            $password = $this->generator->generate();
            $user->setPassword($this->hasher->hashPassword($user, $password), true);
            $this->em->persist($user);
            $created[] = ['user' => $user, 'password' => $password];
        }
        $this->em->flush();

        foreach ($classCache as $name => $class) {
            $this->audit->log(AuditLogger::CLASS_IMPORTED, $teacher->getUserIdentifier(), $name, [
                'eleves' => \count(array_filter($rows, static fn (array $r) => $r['className'] === $name)),
                'annee' => $schoolYear,
            ]);
        }

        return ['created' => $created, 'errors' => []];
    }

    private function findOrCreateClass(User $teacher, string $name, string $levelCode, string $schoolYear): SchoolClass
    {
        $class = $this->classes->findOneByNameAndYear($name, $schoolYear);
        if (null === $class) {
            $class = new SchoolClass($name, $schoolYear, $levelCode, $teacher);
            $this->em->persist($class);
        }

        return $class;
    }

    /** Nouveau mot de passe provisoire (EP2, S6). Renvoie le mot de passe en clair, à afficher une seule fois. */
    public function resetPassword(User $teacher, User $student): string
    {
        $password = $this->generator->generate();
        $student->setPassword($this->hasher->hashPassword($student, $password), true);
        $this->em->flush();
        $this->audit->log(AuditLogger::PASSWORD_RESET, $teacher->getUserIdentifier(), $student->getUserIdentifier());

        return $password;
    }

    public function changeOwnPassword(User $user, string $plainPassword): void
    {
        $user->setPassword($this->hasher->hashPassword($user, $plainPassword), false);
        $this->em->flush();
        $this->audit->log(AuditLogger::PASSWORD_CHANGED, $user->getUserIdentifier());
    }

    /** Fin d'année : la classe est archivée, ses élèves ne peuvent plus se connecter (EP1, R4). */
    public function archiveClass(User $teacher, SchoolClass $class): void
    {
        $class->archive($this->clock->now());
        $this->em->flush();
        $this->audit->log(AuditLogger::CLASS_ARCHIVED, $teacher->getUserIdentifier(), $class->getName(), ['annee' => $class->getSchoolYear()]);
    }
}
