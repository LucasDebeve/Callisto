#!/bin/sh
# Restauration complète d'une sauvegarde (S20) — à tester avant la mise en production puis chaque trimestre.
#   ./bin/ops/restore.sh <fichier.sql.gz.age> <fichier-de-clé-privée-age>
set -eu
cd "$(dirname "$0")/../.."
FILE="${1:?Fichier de sauvegarde}"
KEY="${2:?Clé privée age}"

printf "Restaurer %s ? Les données actuelles seront remplacées. Taper RESTAURER : " "$FILE"
read -r answer
[ "$answer" = "RESTAURER" ] || { echo "Annulé."; exit 1; }

docker compose -f compose.prod.yaml stop app
age -d -i "$KEY" "$FILE" | gunzip | docker compose -f compose.prod.yaml exec -T database sh -c 'psql -v ON_ERROR_STOP=1 -U "$POSTGRES_USER" "$POSTGRES_DB"'
docker compose -f compose.prod.yaml start app
docker compose -f compose.prod.yaml exec app php bin/console doctrine:migrations:status
echo "Restauration terminée. Vérifier la connexion d'un compte de test."
