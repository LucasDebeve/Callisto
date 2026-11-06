<?php

declare(strict_types=1);

namespace App\Content\Entity;

use App\Content\Repository\ContentFileRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Pièce jointe versionnée dans le dépôt de contenus (PDF de TP, fichier Python, image).
 * Les images utilisées dans les cours sont publiques, les autres fichiers exigent une connexion.
 */
#[ORM\Entity(repositoryClass: ContentFileRepository::class)]
#[ORM\Table(name: 'content_file')]
class ContentFile
{
    public const IMAGE_EXTENSIONS = ['png', 'jpg', 'jpeg', 'gif', 'svg', 'webp'];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** Chemin relatif dans le dépôt de contenus, identifiant stable. */
    #[ORM\Column(length: 255, unique: true)]
    private string $path;

    /** Nom du fichier stocké (empreinte SHA-256 + extension). */
    #[ORM\Column(length: 80)]
    private string $storedName;

    #[ORM\Column(length: 100)]
    private string $mimeType;

    #[ORM\Column]
    private int $size;

    #[ORM\Column]
    private bool $public;

    #[ORM\Column]
    private bool $archived = false;

    public function __construct(string $path, string $storedName, string $mimeType, int $size)
    {
        $this->path = $path;
        $this->storedName = $storedName;
        $this->mimeType = $mimeType;
        $this->size = $size;
        $this->public = self::isImagePath($path);
    }

    public static function isImagePath(string $path): bool
    {
        return \in_array(strtolower(pathinfo($path, \PATHINFO_EXTENSION)), self::IMAGE_EXTENSIONS, true);
    }

    public function update(string $storedName, string $mimeType, int $size): void
    {
        $this->storedName = $storedName;
        $this->mimeType = $mimeType;
        $this->size = $size;
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

    public function getPath(): string
    {
        return $this->path;
    }

    public function getFileName(): string
    {
        return basename($this->path);
    }

    public function getStoredName(): string
    {
        return $this->storedName;
    }

    public function getMimeType(): string
    {
        return $this->mimeType;
    }

    public function getSize(): int
    {
        return $this->size;
    }

    public function isPublic(): bool
    {
        return $this->public;
    }

    public function isArchived(): bool
    {
        return $this->archived;
    }
}
