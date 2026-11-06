#!/bin/sh
# Déploiement d'une version (M11). Le retour arrière est la même commande avec la version précédente :
#   ./bin/ops/deploy.sh <version>
#   ./bin/ops/deploy.sh --rollback        (revient à la version notée dans .previous-version)
set -eu
cd "$(dirname "$0")/../.."

if [ "${1:-}" = "--rollback" ]; then
    VERSION=$(cat .previous-version)
else
    VERSION="${1:?Usage : deploy.sh <version>|--rollback}"
fi

CURRENT=$(cat .current-version 2>/dev/null || true)
export CALLISTO_VERSION="$VERSION"

echo "Sauvegarde de la base avant déploiement..."
./bin/ops/backup.sh

docker compose -f compose.prod.yaml pull app caddy
docker compose -f compose.prod.yaml up -d --wait database
# Les migrations sont versionnées et testées en CI ; elles s'exécutent avant le démarrage de la nouvelle version.
docker compose -f compose.prod.yaml run --rm app php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration
docker compose -f compose.prod.yaml up -d --wait app caddy

[ -n "$CURRENT" ] && [ "$CURRENT" != "$VERSION" ] && echo "$CURRENT" > .previous-version
echo "$VERSION" > .current-version

# S17 : vérifier que la base n'est publiée sur aucun port de l'hôte et que DOCKER-USER est intact.
./bin/ops/check-network.sh
echo "Version $VERSION déployée."
