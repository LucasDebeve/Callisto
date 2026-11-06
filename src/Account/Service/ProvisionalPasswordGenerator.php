<?php

declare(strict_types=1);

namespace App\Account\Service;

use Random\Randomizer;

/**
 * Mots de passe provisoires lisibles (à recopier depuis une liste imprimée), par ex. « comete-ruban-47 ».
 * Environ 2^30 combinaisons, suffisant pour un mot de passe à usage unique protégé par la limitation d'essais.
 */
final readonly class ProvisionalPasswordGenerator
{
    private const WORDS = [
        'ambre', 'arbre', 'astre', 'balise', 'bambou', 'barque', 'boussole', 'brume', 'cactus', 'canal',
        'carte', 'castor', 'cerise', 'chemin', 'citron', 'colline', 'comete', 'corail', 'crayon', 'cristal',
        'dune', 'ecorce', 'eclair', 'falaise', 'flocon', 'foret', 'fusee', 'galet', 'glacier', 'grenade',
        'hibou', 'horizon', 'iceberg', 'jardin', 'lagune', 'lanterne', 'loutre', 'lumiere', 'marbre', 'meteore',
        'miroir', 'moulin', 'nuage', 'oasis', 'olive', 'orage', 'orbite', 'pastel', 'phare', 'pigeon',
        'plume', 'pollen', 'prairie', 'quartz', 'radar', 'renard', 'rivage', 'rocher', 'ruban', 'sablier',
        'saphir', 'satellite', 'sentier', 'sirocco', 'sommet', 'source', 'tambour', 'tempete', 'tortue', 'toucan',
        'tunnel', 'vallee', 'velours', 'vigne', 'volcan', 'voilier', 'zephyr', 'zenith', 'lagon', 'cobalt',
    ];

    public function __construct(private Randomizer $randomizer = new Randomizer())
    {
    }

    public function generate(): string
    {
        $words = self::WORDS;
        $first = $words[$this->randomizer->getInt(0, \count($words) - 1)];
        do {
            $second = $words[$this->randomizer->getInt(0, \count($words) - 1)];
        } while ($second === $first);

        return \sprintf('%s-%s-%02d', $first, $second, $this->randomizer->getInt(10, 99));
    }
}
