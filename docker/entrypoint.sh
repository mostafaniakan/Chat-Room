#!/usr/bin/env bash
set -euo pipefail

cd /var/www/html

if [ ! -f .env ]; then
  cp .env.example .env
fi

mkdir -p database storage/app/public storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache

DB_PATH="${DB_DATABASE:-database/database.sqlite}"
PHP_HOST="${PHP_SERVER_HOST:-0.0.0.0}"
PHP_PORT="${PHP_SERVER_PORT:-3000}"
REVERB_BIND_HOST="${REVERB_SERVER_HOST:-0.0.0.0}"
REVERB_BIND_PORT="${REVERB_SERVER_PORT:-8080}"

if [[ "$DB_PATH" != /* ]]; then
  DB_PATH="/var/www/html/$DB_PATH"
fi

mkdir -p "$(dirname "$DB_PATH")"
touch "$DB_PATH"

if [ -z "${APP_KEY:-}" ] && ! grep -q "^APP_KEY=base64:" .env; then
  php artisan key:generate --force
fi

# If APP_KEY came in as an empty env var (common in Docker compose),
# rehydrate it from the generated/copied .env so Laravel sees a valid key.
if [ -z "${APP_KEY:-}" ]; then
  APP_KEY_FROM_FILE="$(grep -E '^APP_KEY=' .env | tail -n 1 | cut -d '=' -f 2- || true)"
  APP_KEY_FROM_FILE="${APP_KEY_FROM_FILE%\"}"
  APP_KEY_FROM_FILE="${APP_KEY_FROM_FILE#\"}"

  if [ -n "$APP_KEY_FROM_FILE" ]; then
    export APP_KEY="$APP_KEY_FROM_FILE"
  fi
fi

php artisan config:clear
php artisan storage:link || true
php artisan migrate --force

php artisan serve --host="$PHP_HOST" --port="$PHP_PORT" &
php artisan reverb:start --host="$REVERB_BIND_HOST" --port="$REVERB_BIND_PORT" &
php artisan schedule:work &

wait -n
