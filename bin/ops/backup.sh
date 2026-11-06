#!/bin/sh
# Sauvegarde chiffrée de la base (S20), à lancer chaque nuit par cron puis à copier hors du VPS.
# Chiffrement asymétrique avec age : seule la clé publique est sur le serveur.
#   BACKUP_DIR        dossier local des sauvegardes (défaut /srv/callisto-backups)
#   AGE_RECIPIENT     clé publique age du destinataire (obligatoire)
#   BACKUP_RETENTION  nombre de jours conservés localement (défaut 30)
set -eu
cd "$(dirname "$0")/../.."
: "${AGE_RECIPIENT:?Clé publique age manquante (AGE_RECIPIENT)}"
BACKUP_DIR="${BACKUP_DIR:-/srv/callisto-backups}"
RETENTION="${BACKUP_RETENTION:-30}"
mkdir -p "$BACKUP_DIR"
umask 077

FILE="$BACKUP_DIR/callisto-$(date +%Y%m%d-%H%M%S).sql.gz.age"
docker compose -f compose.prod.yaml exec -T database sh -c 'pg_dump --no-owner --clean --if-exists -U "$POSTGRES_USER" "$POSTGRES_DB"' \
    | gzip -9 | age -r "$AGE_RECIPIENT" > "$FILE"
echo "Sauvegarde : $FILE"

# Purge locale selon la même durée de conservation (R4). La copie hors VPS suit sa propre rotation.
find "$BACKUP_DIR" -name 'callisto-*.sql.gz.age' -mtime +"$RETENTION" -delete

# Copie hors du VPS si une destination rclone est configurée (par ex. un stockage objet en France).
if [ -n "${RCLONE_REMOTE:-}" ]; then
    rclone copy "$FILE" "$RCLONE_REMOTE"
fi
