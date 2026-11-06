# Format des fichiers de contenu

Les cours, exercices, questions de QCM et le référentiel sont des fichiers Markdown et YAML dans un dépôt
Git (EC1). L'application ne propose aucun éditeur : on modifie les fichiers, la CI du dépôt de contenus
les valide, puis l'enseignant publie depuis l'espace enseignant (page **Contenus**) ou en ligne de commande.

```bash
php bin/console callisto:content:import /chemin/du/depot --check   # valider sans base (CI du dépôt de contenus)
php bin/console callisto:content:import /chemin/du/depot           # rapport, confirmation, publication
```

Un exemple complet se trouve dans `content/`.

## Arborescence

```
niveaux.yaml
<niveau>/                     # dossier nommé comme le code du niveau (snt, premiere, terminale)
  <dossier-du-theme>/
    theme.yaml
    <dossier-du-chapitre>/
      chapitre.yaml           # chapitre + ses savoirs, savoir-faire et compétences
      cours/*.md
      exercices/*.md          # énoncé ; corrigé facultatif dans <nom>.corrige.md
      qcm/*.yaml              # une banque de questions par fichier, rattachée à un savoir
      fichiers/**             # pièces jointes et images
```

Les noms de dossiers ne servent qu'au rangement (préfixez-les par un numéro pour l'ordre). Seuls les
**codes** comptent : ce sont des identifiants stables. Renommer un titre ou réécrire un cours ne fait
jamais perdre une validation. Un code : minuscules, chiffres, `.`, `-` ou `_`, unique dans tout le dépôt.

## niveaux.yaml

```yaml
niveaux:
  - code: snt
    titre: SNT (Seconde)
  - code: premiere
    titre: NSI Première
```

## theme.yaml

```yaml
code: snt-internet
titre: Internet
description: Comment les données circulent d'une machine à l'autre.   # facultatif
ordre: 1                                                               # facultatif
```

## chapitre.yaml

```yaml
code: snt-internet-protocoles
titre: Adresses et protocoles
ordre: 1
elements:
  - code: snt-int-adresse-ip
    type: savoir                 # savoir | savoir-faire | competence
    titre: Connaître le rôle d'une adresse IP
    description: ...             # facultatif
    seuil: 100                   # savoirs : % de bonnes réponses pour valider (défaut 100)
    questions_par_tentative: 10  # savoirs : questions tirées à chaque tentative (défaut 10)
    prerequis: []                # codes d'éléments, éventuellement d'autres chapitres ou thèmes
    position: { x: 0, y: 1 }     # facultatif : force la place de l'étoile (colonne, ligne)
```

Règles vérifiées à l'import :

- les prérequis existent et le graphe ne contient **aucun cycle** ;
- ne mettre que les dépendances certaines, fondées sur le programme officiel ;
- la position est calculée automatiquement de gauche à droite à partir du graphe ; `position` n'est jamais obligatoire.

## Cours (`cours/*.md`)

```markdown
---
code: snt-cours-adresses-paquets
titre: Adresses IP et paquets
ordre: 1
savoirs: [snt-int-adresse-ip, snt-int-paquets]   # savoirs travaillés : lien vers leur QCM
---
## Une adresse pour chaque machine

Texte, **gras**, listes, tableaux, citations...
```

Rendu pris en charge : titres (le titre du cours est en en-tête, commencer à `##`), listes, tableaux,
citations, images, code avec coloration (` ```python `).

**L'HTML brut est interdit** : toute balise est signalée comme erreur à l'import.

### Liens internes

| Lien | Cible |
|---|---|
| `[texte](cours:CODE)` | un cours |
| `[texte](exercice:CODE)` | un exercice |
| `[texte](qcm:CODE_DU_SAVOIR)` | le QCM d'un savoir |
| `[texte](fichier:tp.pdf)` | une pièce jointe du dossier `fichiers/` du chapitre |
| `![description](fichier:schema.svg)` | une image du dossier `fichiers/` du chapitre |

Un lien interne vers une cible absente est une erreur. Les liens externes doivent être en `https://`.

### Pièces jointes

Extensions acceptées : images (`png`, `jpg`, `gif`, `svg`, `webp`), `pdf`, `py`, `txt`, `csv`, `zip`,
`odt`, `ods`, `odp`, `md`, `json`, `html`, `css`, `js`, `sql`, `db` ; 20 Mo au plus.
Les images sont publiques (elles illustrent les cours) ; les autres fichiers exigent une connexion et
sont toujours téléchargés, jamais affichés dans la page.

## Exercices (`exercices/*.md`)

```markdown
---
code: snt-ex-decoupage
titre: Découper un message
ordre: 1
corrige_publie: true          # le corrigé n'est visible que si true
elements: [snt-int-paquets]   # facultatif
---
Énoncé en Markdown.
```

Le corrigé, facultatif, est dans le fichier voisin `<même nom>.corrige.md` (Markdown sans en-tête).
L'élève le dévoile volontairement avec le bouton « Afficher le corrigé ».

## Banques de QCM (`qcm/*.yaml`)

```yaml
savoir: snt-int-tcp           # un savoir (pas un savoir-faire ni une compétence)
questions:
  - code: snt-tcp-q1          # identifiant stable de la question
    type: unique              # unique | multiple | courte
    enonce: Quel est le rôle principal du protocole TCP ?
    choix:
      - texte: Garantir que tous les paquets arrivent
        juste: true
      - texte: Donner une adresse à chaque machine
    explication: Affichée avec la correction.

  - code: snt-paq-q2
    type: unique
    enonce: "Combien de paquets sont créés ?"
    code_source: |            # extrait de code affiché sous l'énoncé (facultatif)
      message = "BONJOUR"
      print(len([message[i:i + 3] for i in range(0, len(message), 3)]))
    langage: python           # défaut : python
    choix: [...]

  - code: snt-tcp-q3
    type: courte
    enonce: Quel sigle désigne ce protocole ?
    reponses_acceptees: ["TCP", "Transmission Control Protocol"]
```

Règles : `unique` a exactement une bonne réponse et au moins deux choix ; `multiple` au moins une bonne
réponse ; `courte` au moins une réponse acceptée. Les réponses courtes sont comparées sans tenir compte
de la casse ni des espaces en trop (EQ7). L'énoncé, les choix et l'explication sont en Markdown.

À chaque tentative, l'application tire `questions_par_tentative` questions au hasard et mélange l'ordre
des réponses. Un savoir sans question n'a pas de QCM (avertissement à l'import).

## Ce que fait la publication

- Rapprochement par code : un élément modifié garde ses validations.
- Un élément, cours, exercice ou question qui disparaît est **archivé**, jamais supprimé (EC5) : il reste
  visible dans l'historique des élèves.
- Le rapport (compteurs, avertissements, erreurs, éléments archivés) est affiché avant toute publication ;
  rien n'est publié tant qu'il reste une erreur.

## CI du dépôt de contenus (M9)

Exemple de workflow pour le dépôt de contenus, qui réutilise l'image de l'application :

```yaml
name: Contenus
on: [push, pull_request]
jobs:
  valider:
    runs-on: ubuntu-24.04
    steps:
      - uses: actions/checkout@v4
      - run: >
          docker run --rm -v "$PWD:/content:ro" -e APP_SECRET=ci
          -e DATABASE_URL=postgresql://x:x@localhost/x?serverVersion=16
          ghcr.io/lucasdebeve/callisto-app:latest
          php bin/console callisto:content:import /content --check
```
