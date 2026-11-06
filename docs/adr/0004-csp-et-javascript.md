# 0004 — CSP stricte avec AssetMapper et Stimulus

Date : 2026-11-06 · Statut : accepté

## Contexte
S14 exige une CSP sans script en ligne. AssetMapper rend une carte d'import (`<script type="importmap">`)
et un petit chargeur, tous deux en ligne.

## Décision
`script-src 'self' 'nonce-…'` : un nonce aléatoire par requête est posé **uniquement** sur ce que génère
`importmap()`. Aucun script en ligne n'est écrit à la main ; le thème est appliqué avant affichage par un
fichier externe (`assets/theme-init.js`). `style-src 'self'` : aucun attribut `style`, les jauges sont en SVG.
Le contenu injecté (Markdown) ne peut pas connaître le nonce.

## Conséquences
Toute nouvelle interaction passe par un contrôleur Stimulus dans `assets/controllers`.
