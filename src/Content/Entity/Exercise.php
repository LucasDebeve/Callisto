<?php

declare(strict_types=1);

namespace App\Content\Entity;

use App\Content\Repository\ExerciseRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Exercice : énoncé et corrigé facultatif (EC4). L'application ne l'exécute ni ne l'évalue.
 */
#[ORM\Entity(repositoryClass: ExerciseRepository::class)]
#[ORM\Table(name: 'content_exercise')]
class Exercise
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 120, unique: true)]
    private string $code;

    #[ORM\Column(length: 120)]
    private string $chapterCode;

    #[ORM\Column(length: 200)]
    private string $title;

    #[ORM\Column(type: Types::TEXT)]
    private string $statementHtml = '';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $solutionHtml = null;

    #[ORM\Column]
    private bool $solutionPublished = false;

    /** @var list<string> */
    #[ORM\Column(type: Types::JSON)]
    private array $itemCodes = [];

    #[ORM\Column]
    private int $position = 0;

    #[ORM\Column]
    private bool $archived = false;

    public function __construct(string $code, string $chapterCode, string $title)
    {
        $this->code = $code;
        $this->chapterCode = $chapterCode;
        $this->title = $title;
    }

    /** @param list<string> $itemCodes */
    public function update(string $chapterCode, string $title, string $statementHtml, ?string $solutionHtml, bool $solutionPublished, array $itemCodes, int $position): void
    {
        $this->chapterCode = $chapterCode;
        $this->title = $title;
        $this->statementHtml = $statementHtml;
        $this->solutionHtml = $solutionHtml;
        $this->solutionPublished = $solutionPublished;
        $this->itemCodes = $itemCodes;
        $this->position = $position;
        $this->archived = false;
    }

    public function archive(): void
    {
        $this->archived = true;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function getChapterCode(): string
    {
        return $this->chapterCode;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getStatementHtml(): string
    {
        return $this->statementHtml;
    }

    /** Le corrigé n'est exposé que s'il est publié. */
    public function getPublishedSolutionHtml(): ?string
    {
        return $this->solutionPublished ? $this->solutionHtml : null;
    }

    public function hasSolution(): bool
    {
        return null !== $this->solutionHtml;
    }

    public function isSolutionPublished(): bool
    {
        return $this->solutionPublished && null !== $this->solutionHtml;
    }

    /** @return list<string> */
    public function getItemCodes(): array
    {
        return $this->itemCodes;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function isArchived(): bool
    {
        return $this->archived;
    }
}
