<?php

declare(strict_types=1);

namespace App\Quiz\Domain;

/**
 * Correction d'une question. Une question rapporte 1 point si la réponse est entièrement juste.
 */
final class AnswerChecker
{
    /**
     * @param list<string>             $correctKeys     clés des choix justes
     * @param list<string>             $acceptedAnswers variantes acceptées (réponse courte)
     * @param list<string>|string|null $answer          réponse de l'élève
     */
    public static function isCorrect(QuestionType $type, array $correctKeys, array $acceptedAnswers, array|string|null $answer): bool
    {
        return match ($type) {
            QuestionType::Single => \is_string($answer) && 1 === \count($correctKeys) && $answer === $correctKeys[0],
            QuestionType::Multiple => \is_array($answer) && self::sameSet($answer, $correctKeys),
            QuestionType::Short => \is_string($answer) && '' !== AnswerNormalizer::normalize($answer)
                && \in_array(AnswerNormalizer::normalize($answer), array_map(AnswerNormalizer::normalize(...), $acceptedAnswers), true),
        };
    }

    /**
     * @param list<string> $a
     * @param list<string> $b
     */
    private static function sameSet(array $a, array $b): bool
    {
        $a = array_values(array_unique($a));
        $b = array_values(array_unique($b));
        sort($a);
        sort($b);

        return $a === $b && [] !== $b;
    }
}
