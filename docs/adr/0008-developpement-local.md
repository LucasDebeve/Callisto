# 0008 — Développement local sans image applicative

Date : 2026-11-06 · Statut : accepté (remplace la partie développement de l'environnement Docker initial)

## Décision
En développement, l'application tourne directement sur le poste via les scripts Composer
(`composer setup`, `composer dev`, `composer test`, `composer qa`). Docker Compose (`compose.yaml`) ne
fournit que les services, aujourd'hui PostgreSQL, publié sur la boucle locale. Les images de l'application
(PHP-FPM) et de Caddy ne sont construites et utilisées qu'en production (`Dockerfile`, `compose.prod.yaml`).

## Conséquences
Débogage et cycle d'édition plus directs, pas de volume monté ni de reconstruction d'image en local.
Le poste doit avoir PHP 8.3 avec intl et pdo_pgsql. La parité avec la production repose sur la CI,
qui construit l'image et rejoue les tests.
