<?php

declare(strict_types=1);

namespace App\Content\Markdown;

/**
 * Liens internes des fichiers sources : « cours:CODE », « exercice:CODE », « qcm:CODE_SAVOIR », « fichier:CHEMIN ».
 * Les chemins de fichiers sont relatifs au dossier du chapitre.
 */
final readonly class InternalLink
{
    public const SCHEMES = ['cours', 'exercice', 'qcm', 'fichier'];

    public function __construct(public string $scheme, public string $target)
    {
    }

    public static function parse(string $url): ?self
    {
        if (!preg_match('/^(cours|exercice|qcm|fichier):([^\s?#]+)(?:#[\w-]+)?$/', $url, $m)) {
            return null;
        }

        return new self($m[1], $m[2]);
    }
}
