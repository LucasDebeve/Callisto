# 0001 — Monolithe Symfony découpé en modules

Date : 2026-11-06 · Statut : accepté

## Contexte
Une seule personne maintient l'outil, quelques heures par an. Il faut pouvoir le reprendre après six mois.

## Décision
Un seul service Symfony 7.4 LTS, découpé en modules par domaine (`Account`, `Referential`, `Content`,
`Quiz`, `Progress`, `Administration`, `Shared`). Chaque module a ses tables préfixées et n'accède à celles
des autres que par leurs services publics (`ReferentialCatalog`, `ContentCatalog`, `ProgressRecorder`…).
Les liens entre modules passent par les codes stables du référentiel. La logique métier pure vit dans
`*/Domain` et se teste sans base.

## Conséquences
Pas d'infrastructure distribuée. La seule exception aux frontières est l'import de contenus, seul
composant autorisé à écrire dans le référentiel, les contenus et la banque de questions.
