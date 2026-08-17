#!/usr/bin/env bash
set -euo pipefail

workspace_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$workspace_dir"

timeout_seconds="${WAIT_TIMEOUT_SECONDS:-300}"
start_time="$(date +%s)"

wait_for_db() {
  while true; do
    container_id="$(docker-compose ps -q db 2>/dev/null || true)"
    if [[ -n "$container_id" ]]; then
      health_status="$(docker inspect --format '{{if .State.Health}}{{.State.Health.Status}}{{else}}unknown{{end}}' "$container_id")"
      if [[ "$health_status" == "healthy" ]]; then
        echo "Database is healthy"
        return 0
      fi
      echo "Waiting for database health: $health_status"
    else
      echo "Waiting for database container"
    fi

    if (( $(date +%s) - start_time > timeout_seconds )); then
      echo "Timed out waiting for healthy database" >&2
      return 1
    fi
    sleep 5
  done
}

wait_for_app() {
  while true; do
    container_id="$(docker-compose ps -q app 2>/dev/null || true)"
    if [[ -n "$container_id" ]]; then
      app_state="$(docker inspect --format '{{.State.Status}}' "$container_id")"
      if [[ "$app_state" == "running" ]]; then
        echo "App container is running"
        return 0
      fi
      echo "Waiting for app container state: $app_state"
    else
      echo "Waiting for app container"
    fi

    if (( $(date +%s) - start_time > timeout_seconds )); then
      echo "Timed out waiting for running app container" >&2
      return 1
    fi
    sleep 5
  done
}

wait_for_db
wait_for_app
