<?php

declare(strict_types=1);

namespace App\Account\Validator;

/**
 * Règles du mot de passe, réutilisées par le validateur et affichées pendant la saisie.
 */
final class PasswordRules
{
    public const TOO_SHORT = 'trop_court';
    public const TOO_COMMON = 'trop_courant';
    public const CONTAINS_USERNAME = 'contient_identifiant';
    public const TOO_SIMPLE = 'trop_simple';

    /** @var array<string, true>|null */
    private static ?array $common = null;

    /** @return list<string> */
    public static function violations(string $password, ?string $username = null): array
    {
        $violations = [];
        if (mb_strlen($password) < AcceptablePassword::MIN_LENGTH) {
            $violations[] = self::TOO_SHORT;
        }
        if (self::isCommon($password)) {
            $violations[] = self::TOO_COMMON;
        }
        if (null !== $username && mb_strlen($username) >= 3 && str_contains(mb_strtolower($password), mb_strtolower($username))) {
            $violations[] = self::CONTAINS_USERNAME;
        }
        $classes = (int) preg_match('/\p{L}/u', $password) + (int) preg_match('/\d/', $password) + (int) preg_match('/[^\p{L}\d]/u', $password);
        if ($classes < 2) {
            $violations[] = self::TOO_SIMPLE;
        }

        return $violations;
    }

    public static function isCommon(string $password): bool
    {
        if (null === self::$common) {
            $list = file(__DIR__.'/common-passwords.txt', \FILE_IGNORE_NEW_LINES | \FILE_SKIP_EMPTY_LINES) ?: [];
            self::$common = array_fill_keys(array_map('mb_strtolower', $list), true);
        }
        $normalized = mb_strtolower($password);
        // Variantes triviales : chiffres ou symboles ajoutés en fin de mot courant.
        $stripped = (string) preg_replace('/[\d\W_]+$/u', '', $normalized);

        return isset(self::$common[$normalized]) || ('' !== $stripped && isset(self::$common[$stripped]) && mb_strlen($stripped) >= 4);
    }
}
