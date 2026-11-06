<?php

declare(strict_types=1);

namespace App\Administration\Repository;

use App\Administration\Entity\AuditEntry;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AuditEntry>
 */
class AuditEntryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AuditEntry::class);
    }

    /** @return list<AuditEntry> */
    public function search(?string $action, ?string $actor, int $page, int $perPage = 50): array
    {
        $qb = $this->createQueryBuilder('a')->orderBy('a.occurredAt', 'DESC')->addOrderBy('a.id', 'DESC');
        if (null !== $action && '' !== $action) {
            $qb->andWhere('a.action = :action')->setParameter('action', $action);
        }
        if (null !== $actor && '' !== $actor) {
            $qb->andWhere('a.actor = :actor OR a.subject = :actor')->setParameter('actor', $actor);
        }

        return $qb->setFirstResult(max(0, $page - 1) * $perPage)->setMaxResults($perPage)->getQuery()->getResult();
    }

    public function countSince(string $action, \DateTimeImmutable $since): int
    {
        return (int) $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->where('a.action = :action')
            ->andWhere('a.occurredAt >= :since')
            ->setParameter('action', $action)
            ->setParameter('since', $since)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function purgeBefore(\DateTimeImmutable $limit): int
    {
        return (int) $this->getEntityManager()->createQuery('DELETE FROM '.AuditEntry::class.' a WHERE a.occurredAt < :limit')
            ->setParameter('limit', $limit)
            ->execute();
    }

    /** @return list<string> */
    public function distinctActions(): array
    {
        return array_column($this->createQueryBuilder('a')->select('DISTINCT a.action')->orderBy('a.action')->getQuery()->getArrayResult(), 'action');
    }
}
