<?php

declare(strict_types=1);

namespace App\Quiz\Domain;

use Random\Randomizer;

/**
 * Tirage aléatoire des questions et mélange des réponses (EQ2).
 */
final readonly class QuestionDrawer
{
    public function __construct(private Randomizer $randomizer = new Randomizer())
    {
    }

    /**
     * @template T
     *
     * @param list<T> $pool
     *
     * @return list<T>
     */
    public function draw(array $pool, int $count): array
    {
        if ([] === $pool || $count <= 0) {
            return [];
        }
        $shuffled = $this->randomizer->shuffleArray($pool);

        return array_values(\array_slice($shuffled, 0, min($count, \count($pool))));
    }

    /**
     * @template T
     *
     * @param list<T> $items
     *
     * @return list<T>
     */
    public function shuffle(array $items): array
    {
        return array_values($this->randomizer->shuffleArray($items));
    }
}
