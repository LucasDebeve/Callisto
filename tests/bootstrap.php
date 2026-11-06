<?php

declare(strict_types=1);

use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\Process\Process;

require dirname(__DIR__).'/vendor/autoload.php';

if (method_exists(Dotenv::class, 'bootEnv')) {
    (new Dotenv())->bootEnv(dirname(__DIR__).'/.env');
}

if ($_SERVER['APP_DEBUG'] ?? false) {
    umask(0o000);
}

// Base de test reconstruite à chaque lancement : migrations, contenu de référence, comptes de démonstration.
// Chaque test s'exécute ensuite dans une transaction annulée (dama/doctrine-test-bundle).
if (!getenv('CALLISTO_SKIP_DB_RESET')) {
    foreach ([
        ['doctrine:database:drop', '--force', '--if-exists'],
        ['doctrine:database:create'],
        ['doctrine:migrations:migrate', '--no-interaction'],
        ['callisto:content:import', '--yes'],
        ['callisto:demo:load'],
        // Compteurs de limitation et codes TOTP déjà utilisés : repartir de zéro à chaque exécution.
        ['cache:pool:clear', 'cache.rate_limiter', 'cache.two_factor'],
    ] as $command) {
        $process = new Process(['php', dirname(__DIR__).'/bin/console', ...$command, '--env=test', '--quiet']);
        $process->setTimeout(120);
        $process->run();
        if (!$process->isSuccessful()) {
            fwrite(\STDERR, implode(' ', $command)." a échoué :\n".$process->getErrorOutput().$process->getOutput());
            exit(1);
        }
    }
}
