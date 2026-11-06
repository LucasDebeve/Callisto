# Sécurité et données personnelles : traçabilité

Chaque exigence du cahier des charges, où elle est mise en œuvre, et le test qui la couvre.

## Authentification et sessions

| Exigence | Mise en œuvre | Vérification |
|---|---|---|
| S1 hachage | `password_hashers: auto` (Argon2id si disponible) ; mots de passe provisoires affichés une fois, jamais stockés ni journalisés | `StudentAccounts`, `AuditLogger` (aucun secret dans `details`) |
| S2 robustesse | `AcceptablePassword` : 10 caractères, liste locale de mots de passe courants, sans l'identifiant, deux sortes de caractères ; changement obligatoire du provisoire (`PasswordChangeRequiredSubscriber`) | `AccountTest`, `StudentJourneyTest::testLoginAndFirstPasswordChangeIsMandatory` |
| S3 limitation | `LoginRateLimiter` : par IP (30/15 min) et par compte quelle que soit l'IP (5/15 min) | — |
| S4 session | cookie `Secure` (auto), `HttpOnly`, `SameSite=Lax` ; expiration après 45 min (élève) / 30 min (enseignant) d'inactivité (`SessionIdleSubscriber`) ; identifiant renouvelé à la connexion (stratégie `migrate`) | — |
| S5 TOTP | scheb/2fa : TOTP obligatoire, compte enseignant refusé sans secret (`UserChecker`), enrôlement en ligne de commande uniquement, codes de secours hachés, refus de réutilisation d'un code | `TeacherJourneyTest::testPasswordAloneIsNotEnough` |
| S6 pas d'e-mail | réinitialisation par l'enseignant seulement | — |

## Contrôle d'accès et intégrité

| Exigence | Mise en œuvre | Vérification |
|---|---|---|
| S7 voters | `SchoolClassVoter`, `StudentVoter`, `AttemptVoter` ; tout contrôle est côté serveur | `AuthorizationMatrixTest` |
| S8 périmètre séparé | pare-feu `teacher` sur `/enseignant`, fournisseurs d'utilisateurs disjoints ; la seule écriture manuelle de validation est `/enseignant/saisie` | `AuthorizationMatrixTest::testEveryRouteIsClassified` |
| S9 isolement des élèves | chaque route est classée ; un élève n'atteint aucune route enseignant ni la tentative d'un autre | `AuthorizationMatrixTest` |
| S10 tentatives | créées et corrigées par le serveur, UUID, propriétaire uniquement, soumission unique, délai maximal (60 min) | `QuizDomainTest`, `StudentJourneyTest::testQuizFlow…` |
| S11 ajout seul | entité en lecture seule sans méthode de modification, garde Doctrine `AppendOnlyGuard`, déclencheur PostgreSQL | `IntegrityTest` |

## Application web

| Exigence | Mise en œuvre |
|---|---|
| S12 CSRF | jetons sur tous les formulaires et sur les requêtes `fetch` du tableau de saisie |
| S13 Markdown | CommonMark `html_input: escape` + HTML brut refusé à l'import + `HtmlSanitizer` par liste blanche (`MarkdownRendererTest`) |
| S14 en-têtes | `SecurityHeadersSubscriber` : CSP `script-src 'self' 'nonce-…'` (le nonce ne sert qu'à la carte d'import d'AssetMapper, voir ADR 0004), `style-src 'self'`, `frame-ancestors 'none'`, HSTS en HTTPS, `nosniff`, `Referrer-Policy` ; Caddy les pose aussi sur les fichiers statiques (`StudentJourneyTest::testSecurityHeaders`) |
| S15 SQL / fichiers | Doctrine et DBAL paramétrés ; aucune réception de fichier : la liste de classe est collée, les contenus sont lus sur le serveur |
| S16 erreurs | pages d'erreur génériques (`templates/bundles/TwigBundle/Exception`), détails dans les journaux |

## Serveur et exploitation

| Exigence | Mise en œuvre |
|---|---|
| S17 isolation | `compose.prod.yaml` : réseau `backend` interne, aucun port publié pour la base ; `bin/ops/check-network.sh` après chaque déploiement |
| S18 secrets | `.env.prod.local` hors Git ou secrets Symfony ; aucun secret versionné |
| S19 images | utilisateur `www-data`, versions épinglées, `composer audit` et Trivy en CI, `cap_drop: ALL` |
| S20 sauvegardes | `bin/ops/backup.sh` (age + copie distante), `bin/ops/restore.sh`, procédure trimestrielle dans le guide |
| S21 audit | à réaliser avant la mise en production (compétence `symfony-security-audit` ou prestataire) |

## Données personnelles

| Exigence | Mise en œuvre |
|---|---|
| R1 minimisation | trois colonnes à l'import ; un nom complet est refusé (`ClassCsvParser`) |
| R2 traceurs | un seul cookie (`callisto_session`) ; polices, icônes et scripts servis par l'application ; préférences en `localStorage` |
| R3 hébergement | OVH en France ; Cloudflare à inscrire comme sous-traitant (mentions légales) |
| R4 suppression | archivage et suppression d'une classe en un geste ; purge des sauvegardes par rotation |
| R5 droits | export JSON et suppression d'un élève par l'enseignant |
| R6 information | `/mentions-legales` (texte à compléter avec le chef d'établissement et le DPD) |
| R7 registre | démarche administrative hors application |

## Limite connue

Un QCM passé à la maison ne peut pas empêcher l'entraide ou le partage de réponses. La banque, le tirage
aléatoire et le mélange réduisent ce risque sans le supprimer ; les savoir-faire et compétences, saisis par
l'enseignant, n'y sont pas exposés.
