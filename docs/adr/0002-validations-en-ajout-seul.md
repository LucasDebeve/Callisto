# 0002 — Validations en ajout seul

Date : 2026-11-06 · Statut : accepté

## Décision
Une validation est un enregistrement daté (validation ou retrait), jamais modifié ni supprimé. L'état
courant se calcule (`ProgressLedger`) à partir du dernier enregistrement. Trois barrières : entité sans
méthode de modification, garde Doctrine `AppendOnlyGuard`, déclencheur PostgreSQL refusant
`UPDATE`/`DELETE`/`TRUNCATE`.

## Exception
La suppression RGPD d'un élève ou d'une classe (R4, R5) lève la protection pour une transaction
(`SET LOCAL callisto.purge = 'on'`), et elle seule.

## Conséquences
L'historique complet est toujours disponible ; une nouvelle saisie sur un élément déjà validé ajoute un
enregistrement qui remplace la date et le commentaire affichés.
