---
code: prem-cours-bases
titre: Bases 2, 10 et 16
ordre: 1
savoirs: [prem-ent-binaire, prem-ent-hexa, prem-ent-relatifs]
---
## La base 2

En base 2, on n'utilise que les chiffres 0 et 1. Chaque position correspond à une puissance de 2 :

| 2³ | 2² | 2¹ | 2⁰ |
|---|---|---|---|
| 8 | 4 | 2 | 1 |

Ainsi `1011` en base 2 vaut 8 + 2 + 1 = 11.

```python
print(bin(11))       # '0b1011'
print(int("1011", 2))  # 11
```

## La base 16

La base 16 utilise les chiffres 0 à 9 puis les lettres A à F. Un chiffre hexadécimal correspond
exactement à quatre bits.

## Les entiers relatifs

Le **complément à deux** permet de coder les entiers négatifs sur un nombre fixe de bits.
