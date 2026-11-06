<?php

declare(strict_types=1);

namespace App\Referential\Domain;

/**
 * Les trois niveaux d'évaluation du référentiel.
 */
enum ItemType: string
{
    case Knowledge = 'savoir';
    case Skill = 'savoir-faire';
    case Competency = 'competence';

    public function label(): string
    {
        return match ($this) {
            self::Knowledge => 'Savoir',
            self::Skill => 'Savoir-faire',
            self::Competency => 'Compétence',
        };
    }

    public function pluralLabel(): string
    {
        return match ($this) {
            self::Knowledge => 'Savoirs',
            self::Skill => 'Savoir-faire',
            self::Competency => 'Compétences',
        };
    }

    /** Les savoirs sont validés par QCM, le reste par l'enseignant. */
    public function isValidatedByQuiz(): bool
    {
        return self::Knowledge === $this;
    }
}
