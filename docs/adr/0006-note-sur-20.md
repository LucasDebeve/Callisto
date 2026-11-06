# 0006 — Calcul indicatif de la note sur 20

Date : 2026-11-06 · Statut : provisoire, à valider

## Contexte
Le cahier des charges exclut la note de la première version, mais la réponse aux points ouverts demande
le calcul, affiché côté enseignant uniquement.

## Décision
`GradeCalculator` : note = 20 × Σ(poids × part validée) / Σ(poids des niveaux présents), sur les éléments
du niveau de l'élève. Poids réglés dans `config/packages/callisto.yaml` (1/1/1 par défaut). Affichée
seulement sur la fiche élève de l'espace enseignant, jamais à l'élève.

## À trancher
La formule réelle (barème par niveau, éléments à prendre en compte, arrondi). L'export CSV viendra plus tard.
