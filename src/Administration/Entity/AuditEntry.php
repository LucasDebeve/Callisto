<?php

declare(strict_types=1);

namespace App\Administration\Entity;

use App\Administration\Repository\AuditEntryRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Journal d'audit (EP7). Aucune donnée secrète n'y figure (jamais de mot de passe, même provisoire).
 */
#[ORM\Entity(repositoryClass: AuditEntryRepository::class, readOnly: true)]
#[ORM\Table(name: 'admin_audit_entry')]
#[ORM\Index(columns: ['occurred_at'])]
#[ORM\Index(columns: ['action'])]
class AuditEntry
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /**
     * @param array<string, scalar|null> $details
     */
    public function __construct(
        #[ORM\Column]
        private \DateTimeImmutable $occurredAt,
        #[ORM\Column(length: 40)]
        private string $action,
        #[ORM\Column(length: 64, nullable: true)]
        private ?string $actor,
        #[ORM\Column(length: 120, nullable: true)]
        private ?string $subject,
        #[ORM\Column(length: 45, nullable: true)]
        private ?string $ipAddress,
        #[ORM\Column(type: Types::JSON)]
        private array $details = [],
    ) {
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOccurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function getAction(): string
    {
        return $this->action;
    }

    public function getActor(): ?string
    {
        return $this->actor;
    }

    public function getSubject(): ?string
    {
        return $this->subject;
    }

    public function getIpAddress(): ?string
    {
        return $this->ipAddress;
    }

    /** @return array<string, scalar|null> */
    public function getDetails(): array
    {
        return $this->details;
    }
}
