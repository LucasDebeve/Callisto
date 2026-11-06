<?php

declare(strict_types=1);

/**
 * Carte d'import d'AssetMapper. Stimulus est versionné localement (assets/lib) : aucun CDN (R2).
 */
return [
    'app' => [
        'path' => './assets/app.js',
        'entrypoint' => true,
    ],
    '@hotwired/stimulus' => [
        'path' => './assets/lib/stimulus.js',
    ],
];
