1. Il faut **3 paquets** (8 caractères, 3 par paquet).
2. `INT`, `ERN`, `ET`.
3. Non : le dernier paquet ne contient que 2 caractères.

```python
message = "INTERNET"
print([message[i:i + 3] for i in range(0, len(message), 3)])
```
