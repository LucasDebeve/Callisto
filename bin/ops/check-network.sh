#!/bin/sh
# S17 : contrôle après chaque changement de configuration réseau.
# Échoue si PostgreSQL est publié sur l'hôte ou si la chaîne DOCKER-USER est vide.
set -eu
cd "$(dirname "$0")/../.."
status=0

if docker compose -f compose.prod.yaml port database 5432 >/dev/null 2>&1; then
    echo "ERREUR : le port 5432 de la base est publié sur l'hôte."
    status=1
fi
if ss -ltn 2>/dev/null | grep -q ':5432 '; then
    echo "ERREUR : un service écoute sur le port 5432 de l'hôte."
    status=1
fi
if command -v iptables >/dev/null 2>&1; then
    rules=$(iptables -S DOCKER-USER 2>/dev/null | grep -vc '^-N\|RETURN$' || true)
    if [ "${rules:-0}" -eq 0 ]; then
        echo "ATTENTION : la chaîne DOCKER-USER ne contient aucune règle de filtrage. Vérifier le pare-feu."
        status=1
    fi
fi
[ "$status" -eq 0 ] && echo "Réseau : base non exposée, DOCKER-USER présent."
exit "$status"
