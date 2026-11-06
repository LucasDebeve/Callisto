<?php

declare(strict_types=1);

namespace App\Progress\Domain;

enum ValidationSource: string
{
    case Quiz = 'qcm';
    case Teacher = 'enseignant';

    public function label(): string
    {
        return match ($this) {
            self::Quiz => 'QCM',
            self::Teacher => 'Enseignant',
        };
    }
}
