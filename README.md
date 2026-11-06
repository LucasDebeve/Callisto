# Callisto

Plateforme de suivi de progression en SNT et NSI : cours, exercices, QCM et validation des savoirs,
savoir-faire et compétences, au même endroit. Chaque thème du référentiel est une constellation dont
les étoiles s'allument au fil des validations.

- **Savoirs** : validés automatiquement par QCM (banque de questions, tirage aléatoire, seuil par savoir).
- **Savoir-faire et compétences** : saisis par l'enseignant dans un tableau classe × critère, utilisable sur téléphone.
- **Contenus** : fichiers Markdown et YAML versionnés dans Git, importés après validation.

Pile technique : PHP 8.3, Symfony 7.4 (LTS), PostgreSQL 16, Twig + Stimulus + AssetMapper + Tailwind CSS,
Caddy, Docker Compose.

## Démarrer en une commande

```bash
docker compose up
```

Puis ouvrir <http://localhost:8080>. Les données de démonstration sont chargées automatiquement :

| Compte | Mot de passe | Accès |
|---|---|---|
| `lea.m`, `tom.b`, `ines.k` | `callisto-demo-2026` | Élèves de SNT |
| `ada.l`, `alan.t` | `callisto-demo-2026` | Élèves de NSI Première |
| `prof` | `callisto-demo-2026` | Enseignant, sur `/enseignant/connexion` |

Le compte enseignant exige un code TOTP. En développement, le code courant s'obtient avec :

```bash
docker compose exec app php bin/console callisto:demo:load --code
```

### Sans Docker

PHP 8.3 (intl, pdo_pgsql), Composer et PostgreSQL 16 :

```bash
composer install
php bin/console doctrine:migrations:migrate -n
php bin/console callisto:content:import --yes
php bin/console callisto:demo:load
php bin/console tailwind:build --watch
```

## Qualité

```bash
vendor/bin/phpunit                                   # tests (base PostgreSQL de test reconstruite)
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
