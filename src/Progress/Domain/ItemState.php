<?php

declare(strict_types=1);

namespace App\Progress\Domain;

/**
 * État courant d'un élément pour un élève, calculé à partir de l'historique.
 */
final readonly class ItemState
{
    public function __construct(
        public bool $validated,
        public ?\DateTimeImmutable $validatedOn = null,
        public ?ValidationSource $source = null,
        public ?string $comment = null,
    ) {
    }

    public static function notValidated(): self
    {
        return new self(false);
    }
}
