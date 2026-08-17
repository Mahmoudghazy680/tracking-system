#!/usr/bin/env bash
set -euo pipefail

workspace_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$workspace_dir"

if [[ ! -f .env ]]; then
  echo ".env not found. Run 'make init-env' and edit it first." >&2
  exit 1
fi

set -a
source ./.env
set +a

docker-compose exec app php artisan key:generate --force
docker-compose exec app php artisan migrate --seed --seeder=InitialSeeder --force
docker-compose exec app php artisan Tracker:make:admin \
  --email="$APP_ADMIN_EMAIL" \
  --name="$APP_ADMIN_NAME" \
  --password="$APP_ADMIN_PASSWORD"

echo "Fallback bootstrap completed"
