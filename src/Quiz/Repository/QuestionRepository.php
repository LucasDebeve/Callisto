<?php

declare(strict_types=1);

namespace App\Quiz\Repository;

use App\Quiz\Entity\Question;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Question>
 */
class QuestionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Question::class);
    }

    /** @return list<Question> */
    public function findActiveForItem(string $itemCode): array
    {
        return $this->findBy(['itemCode' => $itemCode, 'archived' => false], ['code' => 'ASC']);
    }

    /** @return array<string, int> code du savoir => nombre de questions actives */
    public function countActiveByItem(): array
    {
        $rows = $this->createQueryBuilder('q')
            ->select('q.itemCode AS item, COUNT(q.id) AS n')
            ->where('q.archived = false')
            ->groupBy('q.itemCode')
            ->getQuery()
            ->getArrayResult();

        return array_column(array_map(static fn (array $r) => ['item' => (string) $r['item'], 'n' => (int) $r['n']], $rows), 'n', 'item');
    }
}
