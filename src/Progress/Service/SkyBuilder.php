<?php

declare(strict_types=1);

namespace App\Progress\Service;

use App\Progress\Domain\ItemStatus;
use App\Progress\Domain\ProgressLedger;
use App\Referential\Domain\ConstellationLayout;
use App\Referential\Domain\ItemType;
use App\Referential\Entity\Item;
use App\Referential\Entity\Theme;
use App\Referential\Service\ReferentialCatalog;

/**
 * Construit les modèles de vue « Mon ciel » et « Constellation » à partir du référentiel et du registre.
 */
final readonly class SkyBuilder
{
    public function __construct(
        private ReferentialCatalog $referential,
        private ConstellationLayout $layout,
    ) {
    }

    /**
     * @return list<array{theme: Theme, validated: int, total: int, complete: bool, byType: array<string, array{validated: int, total: int}>}>
     */
    public function constellations(string $levelCode, ProgressLedger $ledger): array
    {
        $result = [];
        foreach ($this->referential->themesOf($levelCode) as $theme) {
            $items = $this->referential->itemsOfTheme($theme);
            $codes = array_map(static fn (Item $i) => $i->getCode(), $items);
            $validated = $ledger->countValidated($codes);
            $result[] = [
                'theme' => $theme,
                'validated' => $validated,
                'total' => \count($codes),
                'complete' => [] !== $codes && $validated === \count($codes),
                'byType' => $this->countByType($items, $ledger),
            ];
        }

        return $result;
    }

    /**
     * @param list<Item> $items
     *
     * @return array<string, array{validated: int, total: int}>
     */
    public function countByType(array $items, ProgressLedger $ledger): array
    {
        $counts = [];
        foreach (ItemType::cases() as $type) {
            $ofType = array_values(array_filter($items, static fn (Item $i) => $i->getType() === $type));
            $counts[$type->value] = [
                'validated' => $ledger->countValidated(array_map(static fn (Item $i) => $i->getCode(), $ofType)),
                'total' => \count($ofType),
            ];
        }

        return $counts;
    }

    /**
     * @return list<array{item: Item, status: ItemStatus}>
     */
    public function nextSteps(string $levelCode, ProgressLedger $ledger, int $limit = 3): array
    {
        $codes = array_map(static fn (Item $i) => $i->getCode(), $this->referential->itemsOfLevel($levelCode));
        $graph = $this->referential->graph();

        return array_values(array_filter(array_map(fn (string $code) => null === ($item = $this->referential->item($code)) ? null : [
            'item' => $item,
            'status' => ItemStatus::Available,
        ], $ledger->nextSteps($graph, $codes, $limit))));
    }

    /**
     * Étoiles et liens d'une constellation.
     *
     * @return array{
     *     stars: list<array{item: Item, status: ItemStatus, x: float, y: float, state: \App\Progress\Domain\ItemState, prerequisites: list<array{item: Item, status: ItemStatus}>, successors: list<Item>}>,
     *     links: list<array{from: string, to: string, x1: float, y1: float, x2: float, y2: float, lit: bool}>,
     *     external: list<array{item: Item, status: ItemStatus}>,
     *     width: float,
     *     height: float,
     *     validated: int,
     *     total: int
     * }
     */
    public function constellation(Theme $theme, ProgressLedger $ledger): array
    {
        $items = $this->referential->itemsOfTheme($theme);
        $codes = array_map(static fn (Item $i) => $i->getCode(), $items);
        $graph = $this->referential->graph();
        $local = $graph->restrictedTo($codes);
        $manual = [];
        foreach ($items as $item) {
            if (null !== $item->getManualLayout()) {
                $manual[$item->getCode()] = $item->getManualLayout();
            }
        }
        $positions = $this->layout->compute($local, $codes, $manual);
        $size = $this->layout->size($positions);

        $stars = [];
        $links = [];
        $external = [];
        foreach ($items as $item) {
            $code = $item->getCode();
            $prerequisites = [];
            foreach ($graph->prerequisitesOf($code) as $pre) {
                $preItem = $this->referential->item($pre);
                if (null === $preItem) {
                    continue;
                }
                $status = $ledger->statusOf($pre, $graph);
                $prerequisites[] = ['item' => $preItem, 'status' => $status];
                if (isset($positions[$pre])) {
                    $links[] = [
                        'from' => $pre,
                        'to' => $code,
                        'x1' => $positions[$pre]['x'],
                        'y1' => $positions[$pre]['y'],
                        'x2' => $positions[$code]['x'],
                        'y2' => $positions[$code]['y'],
                        'lit' => $ledger->isValidated($pre),
                    ];
                } else {
                    $external[$pre] = ['item' => $preItem, 'status' => $status];
                }
            }
            $stars[] = [
                'item' => $item,
                'status' => $ledger->statusOf($code, $graph),
                'x' => $positions[$code]['x'],
                'y' => $positions[$code]['y'],
                'state' => $ledger->stateOf($code),
                'prerequisites' => $prerequisites,
                'successors' => array_values(array_filter(array_map(fn (string $s) => $this->referential->item($s), $graph->successorsOf($code)))),
            ];
        }

        return [
            'stars' => $stars,
            'links' => $links,
            'external' => array_values($external),
            'width' => $size['width'],
            'height' => $size['height'],
            'validated' => $ledger->countValidated($codes),
            'total' => \count($codes),
        ];
    }
}
