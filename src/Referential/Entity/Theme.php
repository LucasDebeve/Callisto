<?php

declare(strict_types=1);

namespace App\Referential\Entity;

use App\Referential\Repository\ThemeRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/** Un thème du référentiel = une constellation. */
#[ORM\Entity(repositoryClass: ThemeRepository::class)]
#[ORM\Table(name: 'ref_theme')]
class Theme
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 120, unique: true)]
    private string $code;

    #[ORM\ManyToOne(targetEntity: Level::class)]
    #[ORM\JoinColumn(referencedColumnName: 'code', nullable: false)]
    private Level $level;

    #[ORM\Column(length: 160)]
    private string $title;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column]
    private int $position = 0;

    #[ORM\Column]
    private bool $archived = false;

    /** @var Collection<int, Chapter> */
    #[ORM\OneToMany(targetEntity: Chapter::class, mappedBy: 'theme')]
    private Collection $chapters;

    public function __construct(string $code, Level $level, string $title)
    {
        $this->code = $code;
        $this->level = $level;
        $this->title = $title;
        $this->chapters = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function getLevel(): Level
    {
        return $this->level;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function isArchived(): bool
    {
        return $this->archived;
    }

    public function update(Level $level, string $title, ?string $description, int $position): void
    {
        $this->level = $level;
        $this->title = $title;
        $this->description = $description;
        $this->position = $position;
        $this->archived = false;
    }

    public function archive(): void
    {
        $this->archived = true;
    }

    /** @return list<Chapter> */
    public function getChapters(): array
    {
        $chapters = array_values(array_filter($this->chapters->toArray(), static fn (Chapter $c) => !$c->isArchived()));
        usort($chapters, static fn (Chapter $a, Chapter $b) => $a->getPosition() <=> $b->getPosition());

        return $chapters;
    }
}
