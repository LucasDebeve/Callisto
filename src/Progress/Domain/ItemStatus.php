<?php

declare(strict_types=1);

namespace App\Progress\Domain;

/**
 * Statut d'affichage d'une étoile. « Verrouillé » informe mais n'interdit rien.
 */
enum ItemStatus: string
{
    case Validated = 'valide';
    case Available = 'disponible';
    case Locked = 'verrouille';

    public function label(): string
    {
        return match ($this) {
            self::Validated => 'Validé',
            self::Available => 'Disponible',
            self::Locked => 'Prérequis à valider',
        };
    }
}
