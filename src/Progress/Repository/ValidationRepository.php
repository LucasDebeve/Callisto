<?php

declare(strict_types=1);

namespace App\Progress\Repository;

use App\Account\Entity\User;
use App\Progress\Entity\Validation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Validation>
 */
class ValidationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Validation::class);
    }

    /** @return list<Validation> dans l'ordre d'enregistrement */
    public function findForStudent(User $student): array
    {
        return $this->findBy(['student' => $student], ['recordedAt' => 'ASC', 'id' => 'ASC']);
    }

    /**
     * @param list<User> $students
     *
     * @return array<int, list<Validation>> id élève => enregistrements
     */
    public function findForStudents(array $students): array
    {
        if ([] === $students) {
            return [];
        }
        /** @var list<Validation> $rows */
        $rows = $this->createQueryBuilder('v')
            ->where('v.student IN (:students)')
            ->setParameter('students', $students)
            ->orderBy('v.recordedAt', 'ASC')
            ->addOrderBy('v.id', 'ASC')
            ->getQuery()
            ->getResult();

        $byStudent = [];
        foreach ($rows as $row) {
            $byStudent[(int) $row->getStudent()->getId()][] = $row;
        }

        return $byStudent;
    }

    public function add(Validation $validation): void
    {
        $this->getEntityManager()->persist($validation);
    }
}
