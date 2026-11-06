# 0005 — Espace enseignant : pare-feu propre et TOTP obligatoire

Date : 2026-11-06 · Statut : accepté

## Décision
Deux pare-feux (`main` pour les élèves, `teacher` sur `/enseignant`) avec deux fournisseurs d'utilisateurs
disjoints : un élève ne peut pas s'authentifier côté enseignant et inversement. Le compte enseignant est
créé en ligne de commande avec son secret TOTP et ses codes de secours : il n'existe aucun parcours web
d'enrôlement qu'un attaquant muni du seul mot de passe pourrait utiliser.

## Conséquences
L'enseignant consulte les cours publics comme un visiteur. Ajouter un enseignant = une commande.
