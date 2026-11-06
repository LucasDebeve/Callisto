# 0003 — Contenus en Markdown et YAML dans Git, importés après validation

Date : 2026-11-06 · Statut : accepté

## Décision
Pas d'éditeur dans l'application. Les fichiers sont validés par des schémas (composant Config de Symfony,
déjà présent, plutôt qu'une bibliothèque JSON Schema) puis par des contrôles de cohérence (codes uniques,
graphe sans cycle, bonnes réponses, liens internes, aucun HTML brut). Le HTML est produit et nettoyé à
l'import, puis stocké : l'affichage ne dépend pas du dépôt.

## Conséquences
Le rapport d'import est la seule porte d'entrée des contenus ; un élément disparu est archivé, pas supprimé.
