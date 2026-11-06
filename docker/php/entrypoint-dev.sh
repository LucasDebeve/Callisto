#!/bin/sh
# Environnement de développement : dépendances, base, contenus et démonstration en une commande (M10).
set -e
cd /app
composer install --no-interaction
until php bin/console dbal:run-sql "SELECT 1" >/dev/null 2>&1; do echo "Attente de PostgreSQL..."; sleep 2; done
php bin/console doctrine:migrations:migrate --no-interaction
php bin/console callisto:content:import --yes
php bin/console callisto:demo:load
php bin/console tailwind:build
exec php-fpm --nodaemonize
