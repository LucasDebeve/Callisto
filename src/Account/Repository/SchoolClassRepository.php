<?php

declare(strict_types=1);

namespace App\Account\Repository;

use App\Account\Entity\SchoolClass;
use App\Account\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SchoolClass>
 */
class SchoolClassRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SchoolClass::class);
    }

    /** @return list<SchoolClass> */
    public function findForTeacher(User $teacher, bool $includeArchived = false): array
    {
        $qb = $this->createQueryBuilder('c')
            ->where('c.teacher = :teacher')
            ->setParameter('teacher', $teacher)
            ->orderBy('c.schoolYear', 'DESC')
            ->addOrderBy('c.name', 'ASC');
        if (!$includeArchived) {
            $qb->andWhere('c.archivedAt IS NULL');
        }

        return $qb->getQuery()->getResult();
    }

    public function findOneByNameAndYear(string $name, string $year): ?SchoolClass
    {
        return $this->findOneBy(['name' => $name, 'schoolYear' => $year]);
    }
}
