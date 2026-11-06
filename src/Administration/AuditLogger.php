<?php

declare(strict_types=1);

namespace App\Administration;

use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Point d'entrée unique du journal d'audit. L'écriture est immédiate (flush dédié) pour qu'un échec
 * de connexion soit tracé même si la requête échoue ensuite. On passe par DBAL pour ne jamais
 * déclencher le flush d'autres entités en attente.
 */
final readonly class AuditLogger
{
    public const LOGIN_SUCCESS = 'connexion';
    public const LOGIN_FAILURE = 'connexion_echec';
    public const LOGOUT = 'deconnexion';
    public const TWO_FACTOR_FAILURE = 'double_auth_echec';
    public const PASSWORD_CHANGED = 'mot_de_passe_change';
    public const PASSWORD_RESET = 'mot_de_passe_reinitialise';
    public const VALIDATION_GRANTED = 'validation_saisie';
    public const VALIDATION_REVOKED = 'validation_retiree';
    public const QUIZ_VALIDATION = 'validation_qcm';
    public const CLASS_IMPORTED = 'classe_importee';
    public const CLASS_ARCHIVED = 'classe_archivee';
    public const CLASS_DELETED = 'classe_supprimee';
    public const STUDENT_EXPORTED = 'eleve_exporte';
    public const STUDENT_DELETED = 'eleve_supprime';
    public const CONTENT_IMPORTED = 'contenus_importes';
    public const ACCESS_DENIED = 'acces_refuse';

    public function __construct(
        private Connection $connection,
        private ClockInterface $clock,
        private RequestStack $requestStack,
    ) {
    }

    /**
     * @param array<string, scalar|null> $details
     */
    public function log(string $action, ?string $actor, ?string $subject = null, array $details = []): void
    {
        $this->connection->insert('admin_audit_entry', [
            'occurred_at' => $this->clock->now()->format('Y-m-d H:i:s'),
            'action' => $action,
            'actor' => null === $actor ? null : mb_substr($actor, 0, 64),
            'subject' => null === $subject ? null : mb_substr($subject, 0, 120),
            'ip_address' => $this->requestStack->getMainRequest()?->getClientIp(),
            'details' => json_encode($details, \JSON_THROW_ON_ERROR),
        ]);
    }
}
