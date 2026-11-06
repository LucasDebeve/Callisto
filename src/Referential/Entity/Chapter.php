<?php

declare(strict_types=1);

namespace App\Referential\Entity;

use App\Referential\Repository\ChapterRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ChapterRepository::class)]
#[ORM\Table(name: 'ref_chapter')]
class Chapter
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 120, unique: true)]
    private string $code;

    #[ORM\ManyToOne(targetEntity: Theme::class, inversedBy: 'chapters')]
    #[ORM\JoinColumn(nullable: false)]
    private Theme $theme;

    #[ORM\Column(length: 160)]
    private string $title;

    #[ORM\Column]
    private int $position = 0;

    #[ORM\Column]
    private bool $archived = false;

    /** @var Collection<int, Item> */
    #[ORM\OneToMany(targetEntity: Item::class, mappedBy: 'chapter')]
    private Collection $items;

    public function __construct(string $code, Theme $theme, string $title)
    {
        $this->code = $code;
        $this->theme = $theme;
        $this->title = $title;
        $this->items = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function getTheme(): Theme
    {
        return $this->theme;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function isArchived(): bool
    {
        return $this->archived;
    }

    public function update(Theme $theme, string $title, int $position): void
    {
        $this->theme = $theme;
        $this->title = $title;
        $this->position = $position;
        $this->archived = false;
    }

    public function archive(): void
    {
        $this->archived = true;
    }

    /** @return list<Item> */
    public function getItems(): array
    {
        $items = array_values(array_filter($this->items->toArray(), static fn (Item $i) => !$i->isArchived()));
        usort($items, static fn (Item $a, Item $b) => $a->getPosition() <=> $b->getPosition());

        return $items;
    }
}
