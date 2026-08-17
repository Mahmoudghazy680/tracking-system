#!/usr/bin/env bash
set -euo pipefail

workspace_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$workspace_dir"

if [[ ! -f .env ]]; then
  echo ".env not found. Run 'make init-env' and edit it first." >&2
  exit 1
fi

if ! command -v curl >/dev/null 2>&1; then
  echo "curl is required" >&2
  exit 1
fi

if ! command -v jq >/dev/null 2>&1; then
  echo "jq is required" >&2
  exit 1
fi

set -a
source ./.env
set +a

api_base="${APP_URL%/}/api"

echo "Checking API status at $api_base/status"
status_code="$(curl -sS -o /tmp/cattr-status.json -w '%{http_code}' "$api_base/status")"
if [[ "$status_code" -lt 200 || "$status_code" -ge 400 ]]; then
  echo "Status endpoint failed with HTTP $status_code" >&2
  cat /tmp/cattr-status.json >&2 || true
  exit 1
fi

echo "Attempting admin login"
login_code="$(curl -sS -o /tmp/cattr-login.json -w '%{http_code}' -X POST "$api_base/auth/login" \
  -H 'Content-Type: application/json' \
  -d "{\"email\":\"$APP_ADMIN_EMAIL\",\"password\":\"$APP_ADMIN_PASSWORD\"}")"

if [[ "$login_code" -lt 200 || "$login_code" -ge 400 ]]; then
  echo "Login failed with HTTP $login_code" >&2
  cat /tmp/cattr-login.json >&2 || true
  exit 1
fi

token="$(jq -r '.data.access_token // .access_token // .token // empty' /tmp/cattr-login.json)"

if [[ -z "$token" ]]; then
  echo "Login response did not include a bearer token" >&2
  cat /tmp/cattr-login.json >&2
  exit 1
fi

echo "Checking authenticated user"
me_code="$(curl -sS -o /tmp/cattr-me.json -w '%{http_code}' "$api_base/auth/me" \
  -H "Authorization: Bearer $token")"

if [[ "$me_code" -lt 200 || "$me_code" -ge 400 ]]; then
  echo "Auth me failed with HTTP $me_code" >&2
  cat /tmp/cattr-me.json >&2 || true
  exit 1
fi

echo "Validation succeeded"
echo "Status response:"
cat /tmp/cattr-status.json
echo
echo "Authenticated user response:"
cat /tmp/cattr-me.json
echo
