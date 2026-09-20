#!/usr/bin/env bash
#
# Rotation des secrets de la stack Docker toa-back.
#
# Génère de nouveaux secrets, les écrit dans .env (non versionné), change le mot de passe
# Postgres, régénère la paire de clés JWT, puis recrée les conteneurs et vérifie l'API.
#
# Usage : docker/rotate-secrets.sh [--dry-run]
#
# Effets pour les utilisateurs : les JWT existants deviennent invalides (reconnexion ou refresh).
# Coupure : quelques dizaines de secondes, le temps de recréer php, postgres, minio et mercure.
#
set -euo pipefail

DRY_RUN=0
[[ "${1:-}" == "--dry-run" ]] && DRY_RUN=1

cd "$(dirname "$0")/.."

DOCKER=docker
docker ps >/dev/null 2>&1 || DOCKER="sudo docker"
VERIFY_USER="${VERIFY_USER:-toa@gmail.com}"
BACKUP="$HOME/backups/rotation_$(date +%F_%H%M%S)"

log() { printf '\n==> %s\n' "$*"; }
die() { printf 'ERREUR : %s\n' "$*" >&2; exit 1; }
gen() { openssl rand -hex "$1"; }

# --- Préconditions ---------------------------------------------------------------------------
command -v openssl >/dev/null || die "openssl est requis"
[[ -f .env ]] || die ".env introuvable (à lancer depuis le dépôt du serveur)"
if grep -q '^TOA_' .env; then
    die "des variables TOA_* existent déjà dans .env : la rotation semble déjà faite (supprime le bloc pour la refaire)"
fi
grep -q 'TOA_DB_PASSWORD' docker-compose.yml || die "docker-compose.yml n'est pas templaté (\${TOA_*})"
$DOCKER inspect toa_php toa_postgres toa_minio >/dev/null || die "conteneurs toa_* introuvables"

APP_SECRET=$(gen 32)
JWT_PASSPHRASE=$(gen 32)
MERCURE_SECRET=$(gen 32)
MINIO_USER="toa_$(gen 4)"
MINIO_PASSWORD=$(gen 24)
DB_PASSWORD=$(gen 24)

if (( DRY_RUN )); then
    log "MODE DRY-RUN : rien n'est modifié. Étapes qui seraient exécutées :"
    cat <<EOF
   1. sauvegarde vers $BACKUP : .env, clés JWT, dump complet de la base
   2. ajout de 6 variables TOA_* dans .env (chmod 640, groupe www-data)
   3. ALTER USER toa_user PASSWORD '<nouveau>' dans toa_postgres
   4. nouvelle paire de clés JWT (RSA 4096, passphrase neuve) dans config/jwt, propriétaire www-data
   5. docker compose up -d (recrée php, workers, scheduler, postgres, minio, mercure)
   6. cache:clear en www-data
   7. vérification : /api/health, puis un appel authentifié avec un JWT généré pour $VERIFY_USER
EOF
    exit 0
fi

# --- 1. Sauvegarde ---------------------------------------------------------------------------
log "Sauvegarde vers $BACKUP"
mkdir -p "$BACKUP"
chmod 700 "$BACKUP"
cp -p .env "$BACKUP/env"
sudo cp -p config/jwt/private.pem config/jwt/public.pem "$BACKUP/"
sudo chown -R "$(id -u):$(id -g)" "$BACKUP"
$DOCKER exec toa_postgres pg_dump -U toa_user -d toa_db | gzip > "$BACKUP/db.sql.gz"
chmod 600 "$BACKUP"/*
[[ -s "$BACKUP/db.sql.gz" ]] || die "dump de la base vide, abandon"

# --- 2. Nouveaux secrets dans .env -----------------------------------------------------------
log "Écriture des secrets dans .env"
{
    printf '\n###> docker-compose secrets (ne pas versionner) ###\n'
    printf 'TOA_APP_SECRET=%s\n' "$APP_SECRET"
    printf 'TOA_JWT_PASSPHRASE=%s\n' "$JWT_PASSPHRASE"
    printf 'TOA_MERCURE_SECRET=%s\n' "$MERCURE_SECRET"
    printf 'TOA_MINIO_ROOT_USER=%s\n' "$MINIO_USER"
    printf 'TOA_MINIO_ROOT_PASSWORD=%s\n' "$MINIO_PASSWORD"
    printf 'TOA_DB_PASSWORD=%s\n' "$DB_PASSWORD"
    printf '###< docker-compose secrets ###\n'
} >> .env
# Lisible par le groupe www-data (gid 33) : PHP dans le conteneur charge ce fichier via Dotenv.
sudo chgrp 33 .env
chmod 640 .env

# --- 3. Mot de passe Postgres (les connexions déjà ouvertes restent valides) ------------------
log "Changement du mot de passe Postgres"
printf "ALTER USER toa_user PASSWORD '%s';\n" "$DB_PASSWORD" \
    | $DOCKER exec -i toa_postgres psql -U toa_user -d toa_db -v ON_ERROR_STOP=1

# --- 4. Nouvelle paire de clés JWT -----------------------------------------------------------
log "Régénération des clés JWT"
TMP=$(mktemp -d)
trap 'rm -rf "$TMP"' EXIT
TOA_P="$JWT_PASSPHRASE" openssl genpkey -algorithm RSA -pkeyopt rsa_keygen_bits:4096 \
    -aes256 -pass env:TOA_P -out "$TMP/private.pem"
TOA_P="$JWT_PASSPHRASE" openssl pkey -in "$TMP/private.pem" -passin env:TOA_P -pubout -out "$TMP/public.pem"
sudo install -m 600 -o 33 -g 33 "$TMP/private.pem" config/jwt/private.pem
sudo install -m 644 -o 33 -g 33 "$TMP/public.pem" config/jwt/public.pem

# --- 5. Recréation des conteneurs ------------------------------------------------------------
log "docker compose up -d"
$DOCKER compose up -d

log "Attente de php (healthy)"
for _ in $(seq 1 40); do
    [[ "$($DOCKER inspect -f '{{.State.Health.Status}}' toa_php 2>/dev/null)" == "healthy" ]] && break
    sleep 3
done

# --- 6. Cache --------------------------------------------------------------------------------
log "cache:clear (www-data)"
$DOCKER exec -u www-data toa_php php bin/console cache:clear >/dev/null

# --- 7. Vérification -------------------------------------------------------------------------
log "Vérification"
health=000
for _ in $(seq 1 20); do
    health=$(curl -s -m 10 -o /dev/null -w '%{http_code}' http://localhost:8080/api/health || true)
    [[ "$health" == "200" ]] && break
    sleep 3
done
echo "   /api/health : $health"

token=$($DOCKER exec -u www-data toa_php php bin/console lexik:jwt:generate-token "$VERIFY_USER" \
    --user-class 'App\Domain\User\Entity\User' --ttl 120 2>/dev/null | grep -E '^eyJ' | head -1 || true)
api=000
if [[ -n "$token" ]]; then
    api=$(curl -s -m 20 -o /dev/null -w '%{http_code}' -H "Authorization: Bearer $token" \
        -H 'Accept: application/json' http://localhost:8080/api/plans-prevention || true)
fi
echo "   API authentifiée (JWT signé avec la nouvelle clé) : $api"

if [[ "$health" == "200" && "$api" == "200" ]]; then
    cat <<EOF

Rotation terminée.
 - Secrets : $(pwd)/.env (chmod 640, groupe www-data). Copie-les dans ton gestionnaire de mots de passe.
 - Sauvegarde : $BACKUP (ancien .env, anciennes clés JWT, dump de la base).
 - Les utilisateurs devront se reconnecter (les anciens JWT sont invalides).
 - Committe ensuite docker-compose.yml et .env.docker.example (le .env reste hors dépôt).
EOF
    exit 0
fi

cat >&2 <<EOF

ÉCHEC de la vérification. Retour arrière :
  1. cp -p $BACKUP/env .env
  2. sudo install -m 600 -o 33 -g 33 $BACKUP/private.pem config/jwt/private.pem
     sudo install -m 644 -o 33 -g 33 $BACKUP/public.pem  config/jwt/public.pem
  3. git show 3f77da5:docker-compose.yml > docker-compose.yml   # version avec les anciens secrets
  4. remettre l'ancien mot de passe Postgres (celui de POSTGRES_PASSWORD dans la version du compose ci-dessus) :
     echo "ALTER USER toa_user PASSWORD '<ancien>';" | sudo docker exec -i toa_postgres psql -U toa_user -d toa_db
  5. sudo docker compose up -d
La base n'a pas été modifiée hormis le mot de passe ; un dump complet est dans $BACKUP/db.sql.gz.
EOF
exit 1
