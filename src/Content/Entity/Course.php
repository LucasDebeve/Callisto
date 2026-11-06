<?php

declare(strict_types=1);

namespace App\Content\Entity;

use App\Content\Repository\CourseRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Cours importé depuis le dépôt de contenus. Le HTML est produit à l'import (Markdown sans HTML brut,
 * puis nettoyage par liste blanche).
 */
#[ORM\Entity(repositoryClass: CourseRepository::class)]
#[ORM\Table(name: 'content_course')]
class Course
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 120, unique: true)]
    private string $code;

    /** Code du chapitre du référentiel (lien par identifiant stable, pas de clé étrangère inter-modules). */
    #[ORM\Column(length: 120)]
    private string $chapterCode;

    #[ORM\Column(length: 200)]
    private string $title;

    #[ORM\Column(type: Types::TEXT)]
    private string $html = '';

    /** @var list<array{level: int, id: string, text: string}> */
    #[ORM\Column(type: Types::JSON)]
    private array $toc = [];

    /** @var list<string> savoirs travaillés dans ce cours */
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

    /**
     * @param list<array{level: int, id: string, text: string}> $toc
     * @param list<string>                                      $itemCodes
     */
    public function update(string $chapterCode, string $title, string $html, array $toc, array $itemCodes, int $position): void
    {
        $this->chapterCode = $chapterCode;
        $this->title = $title;
        $this->html = $html;
        $this->toc = $toc;
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

    public function getHtml(): string
    {
        return $this->html;
    }

    /** @return list<array{level: int, id: string, text: string}> */
    public function getToc(): array
    {
        return $this->toc;
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
