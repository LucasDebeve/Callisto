<?php

declare(strict_types=1);

namespace App\Quiz\Domain;

/**
 * Seuil de validation d'un savoir, exprimé en pourcentage de bonnes réponses (EQ3).
 */
final class ThresholdPolicy
{
    public static function isReached(int $score, int $total, int $thresholdPercent): bool
    {
        if ($total <= 0) {
            return false;
        }

        return $score * 100 >= $thresholdPercent * $total;
    }

    /** Nombre de bonnes réponses nécessaires pour atteindre le seuil. */
    public static function requiredScore(int $total, int $thresholdPercent): int
    {
        return (int) ceil($thresholdPercent * $total / 100);
    }
}
