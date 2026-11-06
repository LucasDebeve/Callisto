<?php

declare(strict_types=1);

namespace App\Account\Validator;

use Symfony\Component\Validator\Constraint;

/**
 * S2 : longueur minimale et refus des mots de passe courants (liste locale, aucun appel externe).
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD)]
final class AcceptablePassword extends Constraint
{
    public const MIN_LENGTH = 10;

    public string $tooShort = 'Ton mot de passe doit faire au moins {{ min }} caractères.';
    public string $tooCommon = 'Ce mot de passe est trop courant. Choisis-en un autre.';
    public string $containsUsername = 'Ton mot de passe ne doit pas contenir ton identifiant.';
    public string $tooSimple = 'Ton mot de passe doit mélanger au moins deux sortes de caractères (lettres, chiffres, symboles).';

    public function __construct(public ?string $username = null, mixed $options = null, ?array $groups = null, mixed $payload = null)
    {
        parent::__construct($options, $groups, $payload);
    }
}
