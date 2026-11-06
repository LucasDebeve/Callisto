<?php

declare(strict_types=1);

namespace App\Referential\Entity;

use App\Referential\Domain\ItemType;
use App\Referential\Repository\ItemRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Savoir, savoir-faire ou compétence. Le code est l'identifiant stable choisi par l'enseignant :
 * les validations s'y rattachent, jamais au titre.
 */
#[ORM\Entity(repositoryClass: ItemRepository::class)]
#[ORM\Table(name: 'ref_item')]
class Item
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 120, unique: true)]
    private string $code;

    #[ORM\ManyToOne(targetEntity: Chapter::class, inversedBy: 'items')]
    #[ORM\JoinColumn(nullable: false)]
    private Chapter $chapter;

    #[ORM\Column(length: 20, enumType: ItemType::class)]
    private ItemType $type;

    #[ORM\Column(length: 255)]
    private string $title;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    /** Seuil de validation par QCM, en pourcentage (savoirs uniquement). */
    #[ORM\Column]
    private int $threshold = 100;

    #[ORM\Column]
    private int $questionsPerAttempt = 10;

    #[ORM\Column(nullable: true)]
    private ?float $layoutX = null;

    #[ORM\Column(nullable: true)]
    private ?float $layoutY = null;

    #[ORM\Column]
    private int $position = 0;

    #[ORM\Column]
    private bool $archived = false;

    /** @var Collection<int, Item> */
    #[ORM\ManyToMany(targetEntity: self::class)]
    #[ORM\JoinTable(name: 'ref_item_prerequisite')]
    #[ORM\JoinColumn(name: 'item_id', onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(name: 'prerequisite_id', onDelete: 'CASCADE')]
    private Collection $prerequisites;

    public function __construct(string $code, Chapter $chapter, ItemType $type, string $title)
    {
        $this->code = $code;
        $this->chapter = $chapter;
        $this->type = $type;
        $this->title = $title;
        $this->prerequisites = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function getChapter(): Chapter
    {
        return $this->chapter;
    }

    public function getType(): ItemType
    {
        return $this->type;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getThreshold(): int
    {
        return $this->threshold;
    }

    public function getQuestionsPerAttempt(): int
    {
        return $this->questionsPerAttempt;
    }

    /** @return array{x: float, y: float}|null */
    public function getManualLayout(): ?array
    {
        return null === $this->layoutX || null === $this->layoutY ? null : ['x' => $this->layoutX, 'y' => $this->layoutY];
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function isArchived(): bool
    {
        return $this->archived;
    }

    public function archive(): void
    {
        $this->archived = true;
    }

    public function update(Chapter $chapter, ItemType $type, string $title, ?string $description, int $threshold, int $questionsPerAttempt, ?float $layoutX, ?float $layoutY, int $position): void
    {
        $this->chapter = $chapter;
        $this->type = $type;
        $this->title = $title;
        $this->description = $description;
        $this->threshold = $threshold;
        $this->questionsPerAttempt = $questionsPerAttempt;
        $this->layoutX = $layoutX;
        $this->layoutY = $layoutY;
        $this->position = $position;
        $this->archived = false;
    }

    /** @return list<Item> */
    public function getPrerequisites(): array
    {
        return array_values($this->prerequisites->toArray());
    }

    /** @param list<Item> $prerequisites */
    public function replacePrerequisites(array $prerequisites): void
    {
        $this->prerequisites->clear();
        foreach ($prerequisites as $prerequisite) {
            $this->prerequisites->add($prerequisite);
        }
    }
}
