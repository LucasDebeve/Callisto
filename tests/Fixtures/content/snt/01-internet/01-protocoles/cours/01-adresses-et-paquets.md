---
code: snt-cours-adresses-paquets
titre: Adresses IP et paquets
ordre: 1
savoirs: [snt-int-adresse-ip, snt-int-paquets, snt-int-tcp]
---
## Une adresse pour chaque machine

Sur Internet, chaque machine connectée possède une **adresse IP**. Une adresse IPv4 s'écrit
avec quatre nombres compris entre 0 et 255, séparés par des points :

| Machine | Adresse IP |
|---|---|
| Ordinateur du CDI | 192.168.1.12 |
| Imprimante | 192.168.1.40 |
| Serveur web | 93.184.216.34 |

> Une adresse IP joue le même rôle qu'une adresse postale : elle permet d'acheminer un message.

## Des données découpées en paquets

Un fichier n'est jamais envoyé d'un seul bloc. Il est découpé en **paquets** de petite taille.
Chaque paquet contient :

- l'adresse IP de l'expéditeur ;
- l'adresse IP du destinataire ;
- un morceau des données.

Les paquets peuvent emprunter des chemins différents et arriver dans le désordre.

## Le protocole TCP

Le protocole **TCP** numérote les paquets, vérifie qu'ils sont tous arrivés et redemande ceux qui
manquent. Il garantit que le fichier reçu est identique au fichier envoyé.

Pour simuler un découpage en paquets, on peut écrire :

```python
message = "Bonjour tout le monde"
taille = 5
paquets = [message[i:i + taille] for i in range(0, len(message), taille)]
print(paquets)
```

Voir aussi l'exercice [Découper un message](exercice:snt-ex-decoupage) et le schéma du réseau du lycée :

![Schéma d'un réseau local](fichier:reseau-local.svg)

Tu peux télécharger la [fiche du TP](fichier:tp-reseau.txt) (connexion nécessaire).
