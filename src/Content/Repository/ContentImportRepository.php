<?php

declare(strict_types=1);

namespace App\Content\Repository;

use App\Content\Entity\ContentImport;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ContentImport>
 */
class ContentImportRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ContentImport::class);
    }
}
