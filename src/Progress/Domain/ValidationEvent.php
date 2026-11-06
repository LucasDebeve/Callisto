<?php

declare(strict_types=1);

namespace App\Progress\Domain;

/**
 * Un enregistrement de l'historique : validation ou retrait. Objet valeur pur.
 */
final readonly class ValidationEvent
{
    public function __construct(
        public string $itemCode,
        public bool $granted,
        public \DateTimeImmutable $validatedOn,
        public \DateTimeImmutable $recordedAt,
        public ValidationSource $source,
        public ?string $comment = null,
        public int $sequence = 0,
    ) {
    }
}
