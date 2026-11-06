<?php

declare(strict_types=1);

namespace App\Content\Markdown;

/**
 * Transforme un lien interne en URL. Implémenté par l'import (qui connaît les routes et le chapitre courant).
 */
interface LinkResolver
{
    /** Renvoie l'URL, ou null si la cible est inconnue (le lien est alors signalé comme invalide). */
    public function resolve(InternalLink $link): ?string;
}
