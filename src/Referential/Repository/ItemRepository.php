<?php

declare(strict_types=1);

namespace App\Referential\Repository;

use App\Referential\Entity\Item;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Item>
 */
class ItemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Item::class);
    }

    /** @return list<Item> */
    public function findAllWithStructure(): array
    {
        return $this->createQueryBuilder('i')
            ->addSelect('c', 't', 'l', 'p')
            ->join('i.chapter', 'c')
            ->join('c.theme', 't')
            ->join('t.level', 'l')
            ->leftJoin('i.prerequisites', 'p')
            ->orderBy('l.position', 'ASC')
            ->addOrderBy('t.position', 'ASC')
            ->addOrderBy('c.position', 'ASC')
            ->addOrderBy('i.position', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
