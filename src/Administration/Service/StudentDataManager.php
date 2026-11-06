<?php

declare(strict_types=1);

namespace App\Administration\Service;

use App\Account\Entity\SchoolClass;
use App\Account\Entity\User;
use App\Administration\AuditLogger;
use App\Progress\Repository\ValidationRepository;
use App\Quiz\Repository\AttemptRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Droits RGPD exercés par l'enseignant : export des données d'un élève et suppression (R4, R5).
 *
 * La suppression est la seule opération autorisée à effacer des validations : elle lève, le temps
 * d'une transaction, la protection du déclencheur PostgreSQL (variable de session callisto.purge).
 */
final readonly class StudentDataManager
{
    public function __construct(
        private EntityManagerInterface $em,
        private ValidationRepository $validations,
        private AttemptRepository $attempts,
        private AuditLogger $audit,
    ) {
    }

    /** @return array<string, mixed> */
    public function export(User $teacher, User $student): array
    {
        $data = [
            'compte' => [
                'identifiant' => $student->getUsername(),
                'nom_affiche' => $student->getDisplayName(),
                'classe' => $student->getSchoolClass()?->getName(),
                'annee_scolaire' => $student->getSchoolClass()?->getSchoolYear(),
                'cree_le' => $student->getCreatedAt()->format(\DATE_ATOM),
                'derniere_connexion' => $student->getLastLoginAt()?->format(\DATE_ATOM),
            ],
            'validations' => array_map(static fn ($v) => [
                'element' => $v->getItemCode(),
                'type' => $v->isGranted() ? 'validation' : 'retrait',
                'source' => $v->getSource()->value,
                'date' => $v->getValidatedOn()->format('Y-m-d'),
                'enregistre_le' => $v->getRecordedAt()->format(\DATE_ATOM),
                'commentaire' => $v->getComment(),
            ], $this->validations->findForStudent($student)),
            'tentatives_qcm' => array_map(static fn ($a) => [
                'savoir' => $a->getItemCode(),
                'debut' => $a->getStartedAt()->format(\DATE_ATOM),
                'soumise_le' => $a->getSubmittedAt()?->format(\DATE_ATOM),
                'score' => $a->getScore(),
                'total' => $a->getTotal(),
                'seuil_pourcent' => $a->getThresholdPercent(),
                'reponses' => $a->getAnswers(),
            ], $this->attempts->findAllFor($student)),
        ];
        $this->audit->log(AuditLogger::STUDENT_EXPORTED, $teacher->getUserIdentifier(), $student->getUserIdentifier());

        return $data;
    }

    public function deleteStudent(User $teacher, User $student): void
    {
        $username = $student->getUserIdentifier();
        $this->purge([$student]);
        $this->audit->log(AuditLogger::STUDENT_DELETED, $teacher->getUserIdentifier(), $username);
    }

    /** Supprime la classe et toutes les données de ses élèves, en un geste (R4). */
    public function deleteClass(User $teacher, SchoolClass $class): int
    {
        $students = $class->getStudents();
        $name = $class->getName();
        $year = $class->getSchoolYear();
        $this->purge($students, $class);
        $this->audit->log(AuditLogger::CLASS_DELETED, $teacher->getUserIdentifier(), $name, ['annee' => $year, 'eleves' => \count($students)]);

        return \count($students);
    }

    /**
     * @param list<User> $students
     */
    private function purge(array $students, ?SchoolClass $class = null): void
    {
        $ids = array_values(array_filter(array_map(static fn (User $u) => $u->getId(), $students)));
        $this->em->wrapInTransaction(function () use ($ids, $class): void {
            $connection = $this->em->getConnection();
            $connection->executeStatement("SELECT set_config('callisto.purge', 'on', true)");
            if ([] !== $ids) {
                $connection->executeStatement('DELETE FROM progress_validation WHERE student_id IN (?)', [$ids], [\Doctrine\DBAL\ArrayParameterType::INTEGER]);
                $connection->executeStatement('DELETE FROM quiz_attempt WHERE student_id IN (?)', [$ids], [\Doctrine\DBAL\ArrayParameterType::INTEGER]);
                $connection->executeStatement('DELETE FROM account_user WHERE id IN (?)', [$ids], [\Doctrine\DBAL\ArrayParameterType::INTEGER]);
            }
            if (null !== $class && null !== $class->getId()) {
                $connection->executeStatement('DELETE FROM account_school_class WHERE id = ?', [$class->getId()]);
            }
        });
        $this->em->clear();
    }
}
