<?php

declare(strict_types=1);

namespace App\Content\Entity;

use App\Content\Repository\ContentImportRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/** Trace d'une publication de contenus (rapport conservé). */
#[ORM\Entity(repositoryClass: ContentImportRepository::class)]
#[ORM\Table(name: 'content_import')]
class ContentImport
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /**
     * @param array<string, int> $stats
     * @param list<string>       $warnings
     */
    public function __construct(
        #[ORM\Column]
        private \DateTimeImmutable $importedAt,
        #[ORM\Column(length: 64, nullable: true)]
        private ?string $revision,
        #[ORM\Column(type: Types::JSON)]
        private array $stats,
        #[ORM\Column(type: Types::JSON)]
        private array $warnings,
        #[ORM\Column(length: 64, nullable: true)]
        private ?string $importedBy,
    ) {
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getImportedAt(): \DateTimeImmutable
    {
        return $this->importedAt;
    }

    public function getRevision(): ?string
    {
        return $this->revision;
    }

    /** @return array<string, int> */
    public function getStats(): array
    {
        return $this->stats;
    }

    /** @return list<string> */
    public function getWarnings(): array
    {
        return $this->warnings;
    }

    public function getImportedBy(): ?string
    {
        return $this->importedBy;
    }
}
