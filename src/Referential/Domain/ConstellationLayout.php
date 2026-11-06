<?php

declare(strict_types=1);

namespace App\Referential\Domain;

/**
 * Calcule la position des étoiles d'une constellation, de gauche à droite, à partir du graphe de dépendances.
 *
 * Colonne = rang dans le graphe. Ordre dans la colonne = barycentre des prérequis (réduit les croisements),
 * puis ordre déclaré. Un léger décalage déterministe (dérivé du code) casse l'alignement en grille pour un
 * rendu de ciel plus organique, toujours identique d'un affichage à l'autre. Une position manuelle
 * (fichier YAML) l'emporte toujours et n'est jamais décalée.
 */
final class ConstellationLayout
{
    public const COLUMN_WIDTH = 170;
    public const ROW_HEIGHT = 110;
    public const MARGIN = 70;
    /** Amplitude maximale du décalage organique, en pixels. */
    public const JITTER = 18;

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
                if (isset($manual[$code])) {
                    $positions[$code] = [
                        'x' => self::MARGIN + $manual[$code]['x'] * self::COLUMN_WIDTH,
                        'y' => self::MARGIN + $manual[$code]['y'] * self::ROW_HEIGHT,
                    ];
                    continue;
                }
                [$dx, $dy] = self::jitter($code);
                $positions[$code] = [
                    'x' => self::MARGIN + self::JITTER + $layer * self::COLUMN_WIDTH + $dx,
                    'y' => self::MARGIN + self::JITTER + ($row + $offset) * self::ROW_HEIGHT + $dy,
                ];
            }
        }

        return $positions;
    }

    /**
     * Décalage pseudo-aléatoire mais stable pour un code donné.
     *
     * @return array{0: float, 1: float}
     */
    public static function jitter(string $code): array
    {
        $hash = crc32($code);

        return [
            (($hash & 0xFF) / 255 * 2 - 1) * self::JITTER,
            ((($hash >> 8) & 0xFF) / 255 * 2 - 1) * self::JITTER,
        ];
    }

    /**
     * Chemin SVG courbe (Bézier cubique) d'un prérequis vers l'élément qui en dépend.
     */
    public static function curve(float $x1, float $y1, float $x2, float $y2): string
    {
        $bend = max(40.0, abs($x2 - $x1) * 0.5);

        return \sprintf('M%.1f %.1f C%.1f %.1f %.1f %.1f %.1f %.1f', $x1, $y1, $x1 + $bend, $y1, $x2 - $bend, $y2, $x2, $y2);
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
            'width' => max(array_column($positions, 'x')) + self::MARGIN + self::JITTER + 40,
            'height' => max(array_column($positions, 'y')) + self::MARGIN + self::JITTER + 20,
        ];
    }
}
