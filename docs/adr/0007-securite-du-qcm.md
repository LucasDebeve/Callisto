# 0007 — QCM entièrement côté serveur

Date : 2026-11-06 · Statut : accepté

## Décision
Une tentative (UUID) est créée par le serveur avec un instantané des questions tirées et des réponses
mélangées. Les vues ne reçoivent qu'une projection sans indication de correction ; les réponses sont
enregistrées à chaque changement de question (fonctionne sans JavaScript). La correction a lieu une seule
fois, avant l'échéance (60 min), et crée la validation du savoir une seule fois.

## Conséquences
L'historique reste exact même si la banque change ensuite. Une tentative abandonnée est purgée après 7 jours.
