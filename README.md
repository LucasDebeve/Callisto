# Callisto

Plateforme de suivi de progression en SNT et NSI : cours, exercices, QCM et validation des savoirs,
savoir-faire et compétences, au même endroit. Chaque thème du référentiel est une constellation dont
les étoiles s'allument au fil des validations.

- **Savoirs** : validés automatiquement par QCM (banque de questions, tirage aléatoire, seuil par savoir).
- **Savoir-faire et compétences** : saisis par l'enseignant dans un tableau classe × critère, utilisable sur téléphone.
- **Contenus** : fichiers Markdown et YAML versionnés dans Git, importés après validation.

Pile technique : PHP 8.3, Symfony 7.4 (LTS), PostgreSQL 16, Twig + Stimulus + AssetMapper + Tailwind CSS,
Docker Compose (services de développement, production derrière Caddy).

## Développement local

L'application tourne sur le poste ; seuls les services (PostgreSQL) sont dans Docker.
Les images Docker de l'application et Caddy servent uniquement à la production (`compose.prod.yaml`).

Prérequis : PHP 8.3 (extensions intl, pdo_pgsql), Composer, Docker.

```bash
composer install
composer setup     # services Docker, migrations, contenus, démonstration, feuille de style
composer dev       # http://127.0.0.1:8000 (serveur PHP + Tailwind en surveillance)
```

`composer dev` démarre aussi les services s'ils sont arrêtés. Pour les arrêter : `docker compose down`.
Compte bloqué après trop d'essais de connexion : `composer rate-limit:reset`.
Si un PostgreSQL local occupe déjà le port 5432, lancer avec `CALLISTO_DB_PORT=5433` et adapter
`DATABASE_URL` dans `.env.local`.

Comptes de démonstration :

| Compte | Mot de passe | Accès |
|---|---|---|
| `lea.m`, `tom.b`, `ines.k` | `callisto-demo-2026` | Élèves de SNT |
| `ada.l`, `alan.t` | `callisto-demo-2026` | Élèves de NSI Première |
| `prof` | `callisto-demo-2026` | Enseignant, sur `/enseignant/connexion` |

Le compte enseignant exige un code TOTP. En développement, le code courant s'obtient avec :

```bash
php bin/console callisto:demo:load --code
```

## Qualité

```bash
composer test                                        # tests (base PostgreSQL de test reconstruite)
composer qa                                          # formatage, analyse statique et tests, comme la CI
composer install --working-dir=tools/phpstan
tools/phpstan/vendor/bin/phpstan analyse             # analyse statique, niveau 8
vendor/bin/php-cs-fixer fix                          # formatage
composer audit                                       # vulnérabilités des dépendances
php bin/console callisto:content:import <dossier> --check   # validation d'un dépôt de contenus
```

La CI (`.github/workflows/ci.yml`) bloque tout déploiement si l'une de ces étapes échoue.

## Organisation du code

Un monolithe découpé en modules (`src/`), chacun avec ses entités, ses dépôts et ses services :

| Module | Rôle |
|---|---|
| `Account` | Comptes élèves et enseignant, classes, mots de passe, double authentification |
| `Referential` | Niveaux, thèmes, chapitres, éléments, graphe de dépendances, disposition des constellations |
| `Content` | Rendu Markdown sécurisé, schémas, import et publication des contenus, pages publiques |
| `Quiz` | Banque de questions, tentatives, correction côté serveur |
| `Progress` | Validations en ajout seul, état courant, prochaines étapes, constellations, historique |
| `Administration` | Espace enseignant, journal d'audit, droits RGPD, commandes d'exploitation |
| `Shared` | En-têtes de sécurité, expiration de session, extensions Twig |

La logique métier sans framework vit dans les dossiers `Domain/` (testée sans base de données).

## Documentation

- [Format des fichiers de contenu](docs/contenus.md)
- [Schéma de données](docs/schema-donnees.md)
- [Guide d'exploitation](docs/exploitation.md) : rentrée, fin d'année, sauvegardes, rotation des secrets
- [Sécurité et données personnelles](docs/securite.md) : traçabilité des exigences S1 à S21 et R1 à R7
- [Décisions d'architecture](docs/adr/)
- [Dépendances et justifications](docs/dependances.md)
