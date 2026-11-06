<?php

declare(strict_types=1);

namespace App\Quiz\Repository;

use App\Account\Entity\User;
use App\Quiz\Entity\Attempt;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Attempt>
 */
class AttemptRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Attempt::class);
    }

    /** @return list<Attempt> tentatives soumises, les plus récentes d'abord */
    public function findSubmittedFor(User $student, ?string $itemCode = null, ?int $limit = null): array
    {
        $qb = $this->createQueryBuilder('a')
            ->where('a.student = :student')
            ->andWhere('a.submittedAt IS NOT NULL')
            ->setParameter('student', $student)
            ->orderBy('a.submittedAt', 'DESC');
        if (null !== $itemCode) {
            $qb->andWhere('a.itemCode = :item')->setParameter('item', $itemCode);
        }
        if (null !== $limit) {
            $qb->setMaxResults($limit);
        }

        return $qb->getQuery()->getResult();
    }

    public function findOpenFor(User $student, string $itemCode, \DateTimeImmutable $now): ?Attempt
    {
        return $this->createQueryBuilder('a')
            ->where('a.student = :student')
            ->andWhere('a.itemCode = :item')
            ->andWhere('a.submittedAt IS NULL')
            ->andWhere('a.expiresAt > :now')
            ->setParameter('student', $student)
            ->setParameter('item', $itemCode)
            ->setParameter('now', $now)
            ->orderBy('a.startedAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /** @return list<Attempt> */
    public function findAllFor(User $student): array
    {
        return $this->findBy(['student' => $student], ['startedAt' => 'ASC']);
    }

    /** Tentatives jamais envoyées et expirées depuis longtemps : sans valeur pédagogique. */
    public function purgeAbandonedBefore(\DateTimeImmutable $limit): int
    {
        return (int) $this->getEntityManager()->createQuery('DELETE FROM '.Attempt::class.' a WHERE a.submittedAt IS NULL AND a.expiresAt < :limit')
            ->setParameter('limit', $limit)
            ->execute();
    }
}
