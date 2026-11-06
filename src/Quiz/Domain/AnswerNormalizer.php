<?php

declare(strict_types=1);

namespace App\Quiz\Domain;

/**
 * Normalisation des réponses courtes (EQ7) : espaces en trop et casse ignorés.
 */
final class AnswerNormalizer
{
    public static function normalize(string $answer): string
    {
        $answer = str_replace("\u{00A0}", ' ', $answer);
        $answer = trim((string) preg_replace('/\s+/u', ' ', $answer));

        return mb_strtolower($answer, 'UTF-8');
    }
}
