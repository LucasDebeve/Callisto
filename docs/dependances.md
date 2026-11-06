# Dépendances et justifications (M4)

Toute nouvelle dépendance s'ajoute ici avec sa raison. Préférer un composant Symfony existant.

## Exécution

| Paquet | Pourquoi |
|---|---|
| `symfony/*` 7.4 (LTS) | Cadre applicatif : sécurité, formulaires, validation, limitation de débit, Twig, AssetMapper, HtmlSanitizer, Uid, Clock |
| `doctrine/orm`, `doctrine/doctrine-migrations-bundle` | Persistance PostgreSQL et migrations versionnées (M11) |
| `league/commonmark` | Rendu Markdown conforme, HTML brut désactivable, AST pour vérifier liens et HTML (EC3, S13) |
| `scrivo/highlight.php` | Coloration du code côté serveur : aucun script ni service tiers dans le navigateur (R2, CSP) |
| `scheb/2fa-bundle`, `scheb/2fa-totp`, `scheb/2fa-backup-code` | Double authentification TOTP éprouvée pour Symfony (S5) |
| `symfonycasts/tailwind-bundle` | Compilation Tailwind CSS intégrée à AssetMapper, sans chaîne Node.js (M3) |
| `twig/extra-bundle` | Extensions Twig officielles |

## Front (versionné dans `assets/`)

| Ressource | Pourquoi |
|---|---|
| `@hotwired/stimulus` 3.2 (`assets/lib`) | Comportements progressifs (M3), servi localement |
| Polices Inter, Space Grotesk, JetBrains Mono (OFL) | Typographies du design, hébergées par l'application (R2) |
| Icônes Lucide (ISC) | Jeu d'icônes unique, copié en SVG dans `templates/icons` |

## Développement

| Paquet | Pourquoi |
|---|---|
| `phpunit/phpunit`, `symfony/browser-kit`, `symfony/css-selector` | Tests (M6) |
| `dama/doctrine-test-bundle` | Isolation des tests par transaction |
| `friendsofphp/php-cs-fixer` | Formatage (M7) |
| `phpstan/*` (dans `tools/phpstan`) | Analyse statique (M7), isolée pour ne pas contraindre les versions de l'application |
| `symfony/debug-bundle`, `symfony/web-profiler-bundle`, `symfony/maker-bundle` | Confort de développement |
