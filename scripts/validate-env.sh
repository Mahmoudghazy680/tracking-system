#!/usr/bin/env bash
set -euo pipefail

workspace_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
env_file="$workspace_dir/.env"

if [[ ! -f "$env_file" ]]; then
  echo ".env not found. Run 'make init-env' first." >&2
  exit 1
fi

set -a
source "$env_file"
set +a

required_vars=(
  DOMAIN
  VM_IP
  APP_KEY
  APP_URL
  FRONTEND_APP_URL
  DB_HOST
  DB_PORT
  DB_DATABASE
  DB_USERNAME
  DB_PASSWORD
  MYSQL_DATABASE
  MYSQL_ROOT_PASSWORD
  APP_ADMIN_NAME
  APP_ADMIN_EMAIL
  APP_ADMIN_PASSWORD
)

for var_name in "${required_vars[@]}"; do
  if [[ -z "${!var_name:-}" ]]; then
    echo "Missing required variable: $var_name" >&2
    exit 1
  fi
done

placeholder_vars=(
  APP_KEY
  DB_PASSWORD
  MYSQL_ROOT_PASSWORD
  APP_ADMIN_PASSWORD
)

for var_name in "${placeholder_vars[@]}"; do
  if [[ "${!var_name}" == *REPLACE_WITH* ]]; then
    echo "Variable still contains a placeholder value: $var_name" >&2
    exit 1
  fi
done

expected_url="http://$DOMAIN"

if [[ "$APP_URL" != "$expected_url" ]]; then
  echo "APP_URL must be $expected_url" >&2
  exit 1
fi

if [[ "$FRONTEND_APP_URL" != "$expected_url" ]]; then
  echo "FRONTEND_APP_URL must be $expected_url" >&2
  exit 1
fi

if [[ "$DB_HOST" != "db" ]]; then
  echo "DB_HOST must be db for this compose stack" >&2
  exit 1
fi

echo "Environment validation passed"
