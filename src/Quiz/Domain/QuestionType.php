<?php

declare(strict_types=1);

namespace App\Quiz\Domain;

enum QuestionType: string
{
    case Single = 'unique';
    case Multiple = 'multiple';
    case Short = 'courte';

    public function label(): string
    {
        return match ($this) {
            self::Single => 'Choix unique',
            self::Multiple => 'Choix multiple',
            self::Short => 'Réponse courte',
        };
    }
}
