# Schéma de données

Chaque module possède ses tables (préfixe) et ne lit ni n'écrit celles des autres : les liens entre modules
passent par des **codes stables** (chaînes) ou par l'identifiant du compte (M1).

```
account_user ─┬─< account_school_class (enseignant)
              └─> account_school_class (classe de l'élève)

ref_level ─< ref_theme ─< ref_chapter ─< ref_item >─< ref_item_prerequisite (graphe)

content_course / content_exercise   → chapter_code, item_codes (codes du référentiel)
content_file                        → chemin dans le dépôt de contenus
content_import                      → trace des publications

quiz_question                       → item_code
quiz_attempt (uuid) >─ account_user → item_code, instantané complet des questions

progress_validation >─ account_user → item_code   (ajout seul)

admin_audit_entry                   → identifiants des comptes (texte)
```

## Comptes

| Table | Colonnes notables |
|---|---|
| `account_user` | `username` unique, `display_name` (prénom et initiale), `kind` (`eleve`/`enseignant`), `password` (haché), `must_change_password`, `active`, `school_class_id`, `totp_secret`, `backup_codes` (hachés), `created_at`, `last_login_at` |
| `account_school_class` | `name`, `school_year` (2026-2027), `level_code`, `teacher_id`, `archived_at` |

Aucune adresse e-mail, date de naissance ni photo (R1). Une classe est rattachée à un enseignant :
l'ajout d'autres enseignants ne demandera pas de refonte.

## Référentiel

`ref_level` (code), `ref_theme`, `ref_chapter`, `ref_item` : chacun a un `code` unique, un `title`, une
`position` et un indicateur `archived`. `ref_item` porte `type` (`savoir`, `savoir-faire`, `competence`),
`threshold` (pourcentage), `questions_per_attempt`, `layout_x`/`layout_y` (position manuelle facultative).
`ref_item_prerequisite (item_id, prerequisite_id)` est le graphe de dépendances, sans cycle (vérifié à l'import).

## Contenus

`content_course` et `content_exercise` stockent le HTML déjà rendu et nettoyé à l'import, avec le code
du chapitre et les codes des éléments travaillés. `content_file` référence les pièces jointes stockées
dans `CALLISTO_FILES_DIR` sous un nom d'empreinte SHA-256.

## QCM

- `quiz_question` : `code`, `item_code`, `type`, `prompt_html`, `code_html`, `choices` (JSON : clé, HTML,
  juste), `accepted_answers`, `explanation_html`, `archived`.
- `quiz_attempt` : identifiant UUID (non devinable), élève, `item_code`, `threshold_percent`, `questions`
  (instantané complet des questions tirées, réponses dans l'ordre mélangé), `answers`, `results`, `score`,
  `total`, `passed`, `started_at`, `expires_at`, `submitted_at` (EQ6). L'instantané rend l'historique
  indépendant des modifications ultérieures de la banque.

## Progression

`progress_validation` est un **journal en ajout seul** (S11) :

| Colonne | Sens |
|---|---|
| `student_id`, `item_code` | qui, quoi |
| `granted` | `true` validation, `false` retrait |
| `source` | `qcm` ou `enseignant` |
| `validated_on` | date de validation (modifiable lors de la saisie) |
| `recorded_at` | instant d'enregistrement (ordre de l'historique) |
| `comment`, `author_id`, `attempt_id` | commentaire visible par l'élève, enseignant auteur, tentative à l'origine |

L'état courant d'un élément est celui du dernier enregistrement (`ProgressLedger`). Un déclencheur
PostgreSQL refuse `UPDATE`, `DELETE` et `TRUNCATE` ; seule la purge RGPD (suppression d'un élève ou d'une
classe) lève la protection, le temps d'une transaction, via `SET LOCAL callisto.purge = 'on'`.

## Administration

`admin_audit_entry` : `occurred_at`, `action`, `actor`, `subject`, `ip_address`, `details` (JSON).
Conservé 12 mois (`callisto:purge`).
