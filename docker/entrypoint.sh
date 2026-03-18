#!/bin/sh
set -e

# ============================================================
# CSI — Docker Entrypoint
# ============================================================

echo "=== CSI Container Starting ==="
echo "  APP_ENV: ${APP_ENV:-prod}"
echo "  TZ:      ${TZ:-Africa/Niamey}"

# ── Attendre MySQL ──
if [ -n "$DATABASE_URL" ]; then
    echo "→ Attente de la base de données..."
    DB_HOST=$(echo $DATABASE_URL | sed -n 's/.*@\([^:\/]*\).*/\1/p')
    DB_PORT=$(echo $DATABASE_URL | sed -n 's/.*:\([0-9]*\)\/.*/\1/p')
    DB_PORT=${DB_PORT:-3306}

    for i in $(seq 1 30); do
        if nc -z "$DB_HOST" "$DB_PORT" 2>/dev/null; then
            echo "✓ Base de données disponible ($DB_HOST:$DB_PORT)"
            break
        fi
        echo "  Tentative $i/30..."
        sleep 2
    done
fi

# ── Cache Symfony ──
echo "→ Initialisation du cache Symfony..."
php bin/console cache:warmup --env=${APP_ENV:-prod} --no-debug 2>/dev/null || echo "  ⚠ Cache warmup ignoré"

# ── Migrations auto (si activé) ──
if [ "${AUTO_MIGRATE:-false}" = "true" ]; then
    echo "→ Application des migrations..."
    php bin/console doctrine:migrations:migrate --no-interaction --env=${APP_ENV:-prod} 2>/dev/null || echo "  ⚠ Migrations ignorées"
fi

# ── Permissions ──
chown -R www-data:www-data var public/uploads 2>/dev/null || true
chmod -R 775 var public/uploads 2>/dev/null || true

echo "✓ Démarrage de l'application CSI..."
exec "$@"
