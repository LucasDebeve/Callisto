<?php

declare(strict_types=1);

namespace App\Progress\Domain;

/**
 * Calcul indicatif de la note sur 20, affiché uniquement dans l'espace enseignant.
 *
 * Note = 20 × Σ(poids × part validée) / Σ(poids des niveaux présents). Les poids sont réglés
 * dans config/packages/callisto.yaml. La formule est provisoire et documentée dans docs/adr.
 */
final readonly class GradeCalculator
{
    /**
     * @param array<string, float> $weights type d'élément => poids
     */
    public function __construct(private array $weights)
    {
    }

    /**
     * @param array<string, array{validated: int, total: int}> $counts type d'élément => compteurs
     */
    public function compute(array $counts): ?float
    {
        $weighted = 0.0;
        $totalWeight = 0.0;
        foreach ($counts as $type => $count) {
            $weight = $this->weights[$type] ?? 0.0;
            if ($count['total'] <= 0 || $weight <= 0) {
                continue;
            }
            $weighted += $weight * $count['validated'] / $count['total'];
            $totalWeight += $weight;
        }

        return $totalWeight > 0 ? round(20 * $weighted / $totalWeight, 1) : null;
    }
}
