<?php

declare(strict_types=1);

namespace App\Referential\Domain;

/**
 * Calcule la position des étoiles d'une constellation, de gauche à droite, à partir du graphe de dépendances.
 *
 * Colonne = rang dans le graphe. Ordre dans la colonne = barycentre des prérequis (réduit les croisements),
 * puis ordre déclaré. Une position manuelle (fichier YAML) l'emporte toujours.
 */
final class ConstellationLayout
{
    public const COLUMN_WIDTH = 170;
    public const ROW_HEIGHT = 110;
    public const MARGIN = 70;

    /**
     * @param list<string>                             $orderedCodes ordre déclaré dans le référentiel
     * @param array<string, array{x: float, y: float}> $manual       positions forcées (unités de grille)
     *
     * @return array<string, array{x: float, y: float}> positions en pixels du SVG
     */
    public function compute(DependencyGraph $graph, array $orderedCodes, array $manual = []): array
    {
        $layers = $graph->layers();
        $order = array_flip($orderedCodes);

        /** @var array<int, list<string>> $columns */
        $columns = [];
        foreach ($orderedCodes as $code) {
            if (isset($layers[$code])) {
                $columns[$layers[$code]][] = $code;
            }
        }
        ksort($columns);

        $rowOf = [];
        foreach ($columns as $layer => $codes) {
            if ($layer > 0) {
                usort($codes, function (string $a, string $b) use ($graph, $rowOf, $order): int {
                    $ba = $this->barycenter($graph->prerequisitesOf($a), $rowOf);
                    $bb = $this->barycenter($graph->prerequisitesOf($b), $rowOf);

                    return [$ba, $order[$a] ?? 0] <=> [$bb, $order[$b] ?? 0];
                });
            }
            foreach ($codes as $row => $code) {
                $rowOf[$code] = $row;
            }
            $columns[$layer] = $codes;
        }

        $tallest = max([1, ...array_map('count', $columns)]);
        $positions = [];
        foreach ($columns as $layer => $codes) {
            $offset = ($tallest - \count($codes)) / 2;
            foreach ($codes as $row => $code) {
                $grid = $manual[$code] ?? ['x' => (float) $layer, 'y' => $row + $offset];
                $positions[$code] = [
                    'x' => self::MARGIN + $grid['x'] * self::COLUMN_WIDTH,
                    'y' => self::MARGIN + $grid['y'] * self::ROW_HEIGHT,
                ];
            }
        }

        return $positions;
    }

    /**
     * @param list<string>       $prerequisites
     * @param array<string, int> $rowOf
     */
    private function barycenter(array $prerequisites, array $rowOf): float
    {
        $rows = array_values(array_filter(array_map(static fn (string $p) => $rowOf[$p] ?? null, $prerequisites), static fn ($r) => null !== $r));

        return [] === $rows ? 0.0 : array_sum($rows) / \count($rows);
    }

    /**
     * @param array<string, array{x: float, y: float}> $positions
     *
     * @return array{width: float, height: float}
     */
    public function size(array $positions): array
    {
        if ([] === $positions) {
            return ['width' => 2 * self::MARGIN, 'height' => 2 * self::MARGIN];
        }

        return [
            'width' => max(array_column($positions, 'x')) + self::MARGIN + 40,
            'height' => max(array_column($positions, 'y')) + self::MARGIN + 20,
        ];
    }
}
