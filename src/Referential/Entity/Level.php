<?php

declare(strict_types=1);

namespace App\Referential\Entity;

use App\Referential\Repository\LevelRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: LevelRepository::class)]
#[ORM\Table(name: 'ref_level')]
class Level
{
    #[ORM\Id]
    #[ORM\Column(length: 32)]
    private string $code;

    #[ORM\Column(length: 120)]
    private string $title;

    #[ORM\Column]
    private int $position = 0;

    public function __construct(string $code, string $title)
    {
        $this->code = $code;
        $this->title = $title;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function update(string $title, int $position): void
    {
        $this->title = $title;
        $this->position = $position;
    }
}
