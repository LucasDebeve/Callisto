<?php

declare(strict_types=1);

namespace App\Referential\Service;

use App\Referential\Domain\DependencyGraph;
use App\Referential\Domain\ItemType;
use App\Referential\Entity\Chapter;
use App\Referential\Entity\Item;
use App\Referential\Entity\Level;
use App\Referential\Entity\Theme;
use App\Referential\Repository\ChapterRepository;
use App\Referential\Repository\ItemRepository;
use App\Referential\Repository\LevelRepository;
use App\Referential\Repository\ThemeRepository;

/**
 * API publique du module Référentiel, utilisée par les autres modules (lecture seule).
 */
final class ReferentialCatalog
{
    /** @var array<string, Item>|null */
    private ?array $items = null;

    private ?DependencyGraph $graph = null;

    public function __construct(
        private readonly LevelRepository $levels,
        private readonly ThemeRepository $themes,
        private readonly ChapterRepository $chapters,
        private readonly ItemRepository $itemRepository,
    ) {
    }

    /** @return list<Level> */
    public function levels(): array
    {
        return $this->levels->findBy([], ['position' => 'ASC']);
    }

    public function level(string $code): ?Level
    {
        return $this->levels->find($code);
    }

    /** @return list<Theme> */
    public function themesOf(string $levelCode): array
    {
        return $this->themes->findBy(['level' => $levelCode, 'archived' => false], ['position' => 'ASC']);
    }

    public function theme(string $code): ?Theme
    {
        return $this->themes->findOneBy(['code' => $code]);
    }

    public function chapter(string $code): ?Chapter
    {
        return $this->chapters->findOneBy(['code' => $code]);
    }

    /**
     * Tous les éléments, y compris archivés (pour l'historique).
     *
     * @return array<string, Item>
     */
    public function allItems(): array
    {
        if (null === $this->items) {
            $this->items = [];
            foreach ($this->itemRepository->findAllWithStructure() as $item) {
                $this->items[$item->getCode()] = $item;
            }
        }

        return $this->items;
    }

    public function item(string $code): ?Item
    {
        return $this->allItems()[$code] ?? null;
    }

    /** @return list<Item> éléments actifs du thème, dans l'ordre des chapitres */
    public function itemsOfTheme(Theme $theme): array
    {
        return array_values(array_filter($this->activeItems(), static fn (Item $i) => $i->getChapter()->getTheme() === $theme));
    }

    /** @return list<Item> */
    public function itemsOfLevel(string $levelCode, ?ItemType $type = null): array
    {
        return array_values(array_filter(
            $this->activeItems(),
            static fn (Item $i) => $i->getChapter()->getTheme()->getLevel()->getCode() === $levelCode && (null === $type || $i->getType() === $type),
        ));
    }

    /** @return list<Item> */
    public function itemsOfChapter(Chapter $chapter, ?ItemType $type = null): array
    {
        return array_values(array_filter(
            $this->activeItems(),
            static fn (Item $i) => $i->getChapter() === $chapter && (null === $type || $i->getType() === $type),
        ));
    }

    /** @return list<Item> éléments actifs, dans l'ordre niveau > thème > chapitre > élément */
    public function activeItems(): array
    {
        return array_values(array_filter($this->allItems(), static fn (Item $i) => !$i->isArchived() && !$i->getChapter()->isArchived() && !$i->getChapter()->getTheme()->isArchived()));
    }

    /** Graphe des dépendances entre éléments actifs. */
    public function graph(): DependencyGraph
    {
        if (null === $this->graph) {
            $map = [];
            foreach ($this->activeItems() as $item) {
                $map[$item->getCode()] = array_values(array_map(
                    static fn (Item $p) => $p->getCode(),
                    array_filter($item->getPrerequisites(), static fn (Item $p) => !$p->isArchived()),
                ));
            }
            $this->graph = new DependencyGraph($map);
        }

        return $this->graph;
    }

    public function reset(): void
    {
        $this->items = null;
        $this->graph = null;
    }
}
