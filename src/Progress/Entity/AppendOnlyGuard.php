<?php

declare(strict_types=1);

namespace App\Progress\Entity;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Events;

/**
 * Seconde barrière applicative (la première est le déclencheur PostgreSQL) : toute tentative
 * de modification ou de suppression d'une validation via Doctrine échoue (S11).
 */
#[AsDoctrineListener(event: Events::onFlush)]
final class AppendOnlyGuard
{
    public function onFlush(OnFlushEventArgs $args): void
    {
        $uow = $args->getObjectManager()->getUnitOfWork();
        foreach ([...$uow->getScheduledEntityUpdates(), ...$uow->getScheduledEntityDeletions()] as $entity) {
            if ($entity instanceof Validation) {
                throw new \LogicException('Les validations sont en ajout seul : utiliser un enregistrement de retrait.');
            }
        }
    }
}
